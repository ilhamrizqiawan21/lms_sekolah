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
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtAttemptShuffleTest extends TestCase
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

    public function test_attempt_initialization_shuffles_and_is_idempotent(): void
    {
        $guru = $this->makeUser('Guru Mapel', 'guru');
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'KIM', 'nama_mapel' => 'Kimia', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '10', 'nama_kelas' => '10-A']);

        $km = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);

        $userSiswa = $this->makeUser('Siswa 1', 'siswa');
        $siswa = $this->createSiswa($userSiswa, $kelas);

        $ujian = Ujian::create([
            'kelas_mapel_id' => $km->id,
            'judul' => 'Ujian Kimia Dasar',
            'durasi_menit' => 60,
            'kategori_nilai' => 'NH',
            'acak_soal' => true,
            'acak_opsi' => true,
        ]);

        $soalList = [];
        for ($i = 1; $i <= 5; $i++) {
            $s = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => "Soal {$i}", 'kesulitan' => 'sedang']);
            for ($o = 1; $o <= 4; $o++) {
                SoalBankOpsi::create(['soal_bank_id' => $s->id, 'teks_opsi' => "Opsi {$o}", 'is_benar' => $o === 1, 'urutan' => $o]);
            }
            $us = UjianSoal::create(['ujian_id' => $ujian->id, 'soal_bank_id' => $s->id, 'poin' => 20, 'urutan' => $i]);
            $soalList[] = $us->id;
        }

        // Call mulai first time
        $response1 = $this->actingAs($userSiswa)->post(route('siswa.ujian.mulai', $ujian->id));
        $this->assertEquals(1, UjianAttempt::where('ujian_id', $ujian->id)->where('siswa_id', $siswa->id)->count());

        $attempt = UjianAttempt::where('ujian_id', $ujian->id)->where('siswa_id', $siswa->id)->first();
        $response1->assertRedirect(route('siswa.ujian.kerjakan', $attempt->id));

        $this->assertNotEmpty($attempt->urutan_soal_ids);
        $this->assertCount(5, $attempt->urutan_soal_ids);
        $this->assertEqualsCanonicalizing($soalList, $attempt->urutan_soal_ids);

        // Call mulai second time (should not duplicate)
        $response2 = $this->actingAs($userSiswa)->post(route('siswa.ujian.mulai', $ujian->id));
        $response2->assertRedirect(route('siswa.ujian.kerjakan', $attempt->id));
        $this->assertEquals(1, UjianAttempt::where('ujian_id', $ujian->id)->where('siswa_id', $siswa->id)->count());
    }
}
