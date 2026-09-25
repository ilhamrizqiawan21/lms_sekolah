<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\NilaiAkhir;
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

class CbtNilaiAkhirSyncTest extends TestCase
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

    public function test_cbt_submit_syncs_to_nilai_akhir_and_preserves_other_columns(): void
    {
        $guru = $this->makeUser('Guru Mapel', 'guru');
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'FIS', 'nama_mapel' => 'Fisika', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '10', 'nama_kelas' => '10-IPA']);

        $km = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);

        $userSiswa = $this->makeUser('Siswa Ujian', 'siswa');
        $siswa = $this->createSiswa($userSiswa, $kelas);

        // Pre-create NilaiAkhir with existing manual sum1 and sts
        NilaiAkhir::create([
            'siswa_id' => $siswa->id,
            'kelas_mapel_id' => $km->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'sum1' => 88.0,
            'sts' => 70.0,
        ]);

        $soal = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => 'Soal STS Fisika', 'kesulitan' => 'sedang']);
        $opsiBenar = SoalBankOpsi::create(['soal_bank_id' => $soal->id, 'teks_opsi' => 'Benar', 'is_benar' => true]);
        $opsiSalah = SoalBankOpsi::create(['soal_bank_id' => $soal->id, 'teks_opsi' => 'Salah', 'is_benar' => false]);

        $ujianSTS = Ujian::create([
            'kelas_mapel_id' => $km->id,
            'judul' => 'STS Fisika Ganjil',
            'durasi_menit' => 60,
            'kategori_nilai' => 'STS',
            'acak_soal' => false,
            'acak_opsi' => false,
        ]);

        $us = UjianSoal::create(['ujian_id' => $ujianSTS->id, 'soal_bank_id' => $soal->id, 'poin' => 100, 'urutan' => 1]);

        $this->actingAs($userSiswa)->post(route('siswa.ujian.mulai', $ujianSTS->id));
        $attempt = UjianAttempt::where('ujian_id', $ujianSTS->id)->where('siswa_id', $siswa->id)->first();
        $jawaban = UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)->where('ujian_soal_id', $us->id)->first();

        // Answer correctly
        $this->actingAs($userSiswa)->postJson(route('siswa.ujian.jawab', $attempt->id), [
            'attempt_jawaban_id' => $jawaban->id,
            'jawaban_opsi_id' => $opsiBenar->id,
        ]);

        // Submit
        $this->actingAs($userSiswa)->post(route('siswa.ujian.submit', $attempt->id));

        $nilaiAkhir = NilaiAkhir::where([
            'siswa_id' => $siswa->id,
            'kelas_mapel_id' => $km->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
        ])->first();

        $this->assertNotNull($nilaiAkhir);
        // sum1 is preserved
        $this->assertEquals(88.0, (float) $nilaiAkhir->sum1);
        // sts is overwritten with CBT score 100
        $this->assertEquals(100.0, (float) $nilaiAkhir->sts);
    }
}
