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
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtTimeExpiryAutoSubmitTest extends TestCase
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

    public function test_lazy_auto_submit_on_time_expiry(): void
    {
        $guru = $this->makeUser('Guru Mapel', 'guru');
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'SEJ', 'nama_mapel' => 'Sejarah', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '10', 'nama_kelas' => '10-C']);

        $km = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);

        $userSiswa = $this->makeUser('Siswa Telat', 'siswa');
        $siswa = $this->createSiswa($userSiswa, $kelas);

        $soal = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => 'Soal Sejarah', 'kesulitan' => 'mudah']);
        $opsiBenar = SoalBankOpsi::create(['soal_bank_id' => $soal->id, 'teks_opsi' => 'Benar', 'is_benar' => true]);

        $ujian = Ujian::create([
            'kelas_mapel_id' => $km->id,
            'judul' => 'Ujian Sejarah',
            'durasi_menit' => 10,
            'kategori_nilai' => 'NH',
        ]);
        $us = UjianSoal::create(['ujian_id' => $ujian->id, 'soal_bank_id' => $soal->id, 'poin' => 100, 'urutan' => 1]);

        $this->actingAs($userSiswa)->post(route('siswa.ujian.mulai', $ujian->id));
        $attempt = UjianAttempt::where('ujian_id', $ujian->id)->where('siswa_id', $siswa->id)->first();
        $jawaban = UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)->first();

        // Answer
        $this->actingAs($userSiswa)->postJson(route('siswa.ujian.jawab', $attempt->id), [
            'attempt_jawaban_id' => $jawaban->id,
            'jawaban_opsi_id' => $opsiBenar->id,
        ]);

        // Manipulate batas_waktu to past
        $attempt->update(['batas_waktu' => Carbon::now()->subMinutes(5)]);

        // Try answering after expiry (should be 422)
        $this->actingAs($userSiswa)->postJson(route('siswa.ujian.jawab', $attempt->id), [
            'attempt_jawaban_id' => $jawaban->id,
            'jawaban_opsi_id' => $opsiBenar->id,
        ])->assertStatus(422);

        // Access kerjakan (should trigger lazy auto-submit and redirect to hasil)
        $response = $this->actingAs($userSiswa)->get(route('siswa.ujian.kerjakan', $attempt->id));
        $response->assertRedirect(route('siswa.ujian.hasil', $attempt->id));

        $attempt->refresh();
        $this->assertEquals(UjianAttempt::STATUS_WAKTU_HABIS, $attempt->status);
        $this->assertEquals(100.0, (float) $attempt->skor_total);
    }
}
