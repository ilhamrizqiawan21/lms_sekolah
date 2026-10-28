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

class CbtTabSwitchLogTest extends TestCase
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

    public function test_tab_switch_logging_and_guards(): void
    {
        $guru = $this->makeUser('Guru Mapel', 'guru');
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'TIK', 'nama_mapel' => 'Informatika', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '10', 'nama_kelas' => '10-D']);

        $km = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);

        $userSiswa = $this->makeUser('Siswa TIK', 'siswa');
        $siswa = $this->createSiswa($userSiswa, $kelas);

        $soal = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => 'Soal TIK', 'kesulitan' => 'mudah']);
        $opsi = SoalBankOpsi::create(['soal_bank_id' => $soal->id, 'teks_opsi' => 'A', 'is_benar' => true]);

        $ujian = Ujian::create([
            'kelas_mapel_id' => $km->id,
            'judul' => 'Ujian TIK',
            'durasi_menit' => 30,
            'kategori_nilai' => 'NH',
        ]);
        UjianSoal::create(['ujian_id' => $ujian->id, 'soal_bank_id' => $soal->id, 'poin' => 10, 'urutan' => 1]);

        $this->actingAs($userSiswa)->post(route('siswa.ujian.mulai', $ujian->id));
        $attempt = UjianAttempt::where('ujian_id', $ujian->id)->where('siswa_id', $siswa->id)->first();

        // Switch tab 3 times
        $this->actingAs($userSiswa)->post(route('siswa.ujian.tab-switch', $attempt->id))->assertNoContent();
        $this->actingAs($userSiswa)->post(route('siswa.ujian.tab-switch', $attempt->id))->assertNoContent();
        $this->actingAs($userSiswa)->post(route('siswa.ujian.tab-switch', $attempt->id))->assertNoContent();

        $attempt->refresh();
        $this->assertEquals(3, $attempt->tab_switch_count);
        $this->assertCount(3, $attempt->tab_switch_log);

        // Submit attempt
        $this->actingAs($userSiswa)->post(route('siswa.ujian.submit', $attempt->id));

        // Switch tab after submit (count should remain 3)
        $this->actingAs($userSiswa)->post(route('siswa.ujian.tab-switch', $attempt->id));
        $attempt->refresh();
        $this->assertEquals(3, $attempt->tab_switch_count);
    }
}
