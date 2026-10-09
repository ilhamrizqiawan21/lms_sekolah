<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\SoalBank;
use App\Models\SoalBankOpsi;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianAttemptJawaban;
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtSubmitAutoGradingTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $nama, string $role): User
    {
        $roleModel = Role::firstOrCreate(['nama_role' => $role]);

        return User::create([
            'username' => strtolower(str_replace(' ', '-', $nama)).'-'.uniqid(),
            'email' => strtolower(str_replace(' ', '-', $nama)).'-'.uniqid().'@example.test',
            'password' => Hash::make('secret'),
            'is_password_default' => false,
            'nama_lengkap' => $nama,
            'nip_nis' => null,
            'jenis_kelamin' => null,
            'foto' => null,
            'role_id' => $roleModel->id,
            'is_active' => true,
        ]);
    }

    private function createSiswa(User $user, Kelas $kelas): Siswa
    {
        return Siswa::create([
            'user_id' => $user->id,
            'kelas_id' => $kelas->id,
            'nama_lengkap' => $user->nama_lengkap,
            'nis' => 'NIS'.uniqid(),
            'status' => 'aktif',
        ]);
    }

    public function test_student_submit_calculates_correct_score(): void
    {
        $guru = $this->makeUser('Guru Biologi', 'guru');
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'BIO', 'nama_mapel' => 'Biologi', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '9', 'nama_kelas' => '9B']);

        $km = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);

        $userSiswa = $this->makeUser('Siswa Pintar', 'siswa');
        $siswa = $this->createSiswa($userSiswa, $kelas);

        $soal1 = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => 'Soal 1', 'kesulitan' => 'mudah']);
        $opsi1Benar = SoalBankOpsi::create(['soal_bank_id' => $soal1->id, 'teks_opsi' => 'Benar 1', 'is_benar' => true]);
        $opsi1Salah = SoalBankOpsi::create(['soal_bank_id' => $soal1->id, 'teks_opsi' => 'Salah 1', 'is_benar' => false]);

        $soal2 = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => 'Soal 2', 'kesulitan' => 'sedang']);
        $opsi2Benar = SoalBankOpsi::create(['soal_bank_id' => $soal2->id, 'teks_opsi' => 'Benar 2', 'is_benar' => true]);
        $opsi2Salah = SoalBankOpsi::create(['soal_bank_id' => $soal2->id, 'teks_opsi' => 'Salah 2', 'is_benar' => false]);

        $ujian = Ujian::create([
            'kelas_mapel_id' => $km->id,
            'judul' => 'Ujian 2 Soal',
            'durasi_menit' => 30,
            'kategori_nilai' => 'NH',
            'acak_soal' => false,
            'acak_opsi' => false,
        ]);

        $us1 = UjianSoal::create(['ujian_id' => $ujian->id, 'soal_bank_id' => $soal1->id, 'poin' => 60, 'urutan' => 1]);
        $us2 = UjianSoal::create(['ujian_id' => $ujian->id, 'soal_bank_id' => $soal2->id, 'poin' => 40, 'urutan' => 2]);

        // Siswa starts attempt
        $this->actingAs($userSiswa)->post(route('siswa.ujian.mulai', $ujian->id));
        $attempt = UjianAttempt::where('ujian_id', $ujian->id)->where('siswa_id', $siswa->id)->first();

        $jawaban1 = UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)->where('ujian_soal_id', $us1->id)->first();
        $jawaban2 = UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)->where('ujian_soal_id', $us2->id)->first();

        // Jawab soal 1 BENAR
        $this->actingAs($userSiswa)->postJson(route('siswa.ujian.jawab', $attempt->id), [
            'attempt_jawaban_id' => $jawaban1->id,
            'jawaban_opsi_id' => $opsi1Benar->id,
        ]);

        // Jawab soal 2 SALAH
        $this->actingAs($userSiswa)->postJson(route('siswa.ujian.jawab', $attempt->id), [
            'attempt_jawaban_id' => $jawaban2->id,
            'jawaban_opsi_id' => $opsi2Salah->id,
        ]);

        // Submit ujian
        $response = $this->actingAs($userSiswa)->post(route('siswa.ujian.submit', $attempt->id));
        $response->assertRedirect(route('siswa.ujian.hasil', $attempt->id));

        $attempt->refresh();
        $this->assertEquals(UjianAttempt::STATUS_SELESAI, $attempt->status);
        $this->assertEquals(60.0, (float) $attempt->skor_total);
        $this->assertEquals(100.0, (float) $attempt->skor_maksimal);

        $jawaban1->refresh();
        $this->assertTrue((bool) $jawaban1->is_benar);
        $this->assertEquals(60.0, (float) $jawaban1->poin_didapat);

        $jawaban2->refresh();
        $this->assertFalse((bool) $jawaban2->is_benar);
        $this->assertEquals(0.0, (float) $jawaban2->poin_didapat);
    }
}
