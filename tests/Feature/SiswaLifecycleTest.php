<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class SiswaLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_student_with_academic_history_is_blocked(): void
    {
        [$admin, $guru, $kelas, $tahunAjaran] = $this->fixture();
        $kelasMapel = $this->course($guru, $kelas, $tahunAjaran);
        $siswaUser = $this->createUser('siswa-history', 'Siswa History', 'siswa');
        $siswa = Siswa::create(['user_id' => $siswaUser->id, 'nis' => '9601', 'kelas_id' => $kelas->id, 'status' => 'aktif']);
        Absensi::create([
            'siswa_id' => $siswa->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.kelas-siswa.destroy-siswa', $siswa))
            ->assertRedirect();

        $this->assertDatabaseHas('siswa', ['id' => $siswa->id]);
        $this->assertDatabaseHas('users', ['id' => $siswaUser->id]);
    }

    public function test_deleting_a_student_without_academic_history_succeeds(): void
    {
        [$admin, , $kelas] = $this->fixture();
        $siswaUser = $this->createUser('siswa-no-history', 'Siswa No History', 'siswa');
        $siswa = Siswa::create(['user_id' => $siswaUser->id, 'nis' => '9602', 'kelas_id' => $kelas->id, 'status' => 'aktif']);

        $this->actingAs($admin)
            ->delete(route('admin.kelas-siswa.destroy-siswa', $siswa))
            ->assertRedirect();

        $this->assertDatabaseMissing('siswa', ['id' => $siswa->id]);
        $this->assertDatabaseMissing('users', ['id' => $siswaUser->id]);
    }

    private function fixture(): array
    {
        Role::create(['nama_role' => 'admin']);
        Role::create(['nama_role' => 'guru']);
        Role::create(['nama_role' => 'siswa']);

        $admin = $this->createUser('admin-lifecycle', 'Admin Lifecycle', 'admin');
        $guru = $this->createUser('guru-lifecycle', 'Guru Lifecycle', 'guru');
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);

        return [$admin, $guru, $kelas, $tahunAjaran];
    }

    private function course(User $guru, Kelas $kelas, TahunAjaran $tahunAjaran): KelasMapel
    {
        $mapel = MataPelajaran::create(['kode' => 'LFC', 'nama_mapel' => 'Lifecycle Test', 'urutan' => 1]);

        return KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);
    }

    private function createUser(string $username, string $namaLengkap, string $roleName): User
    {
        $role = Role::where('nama_role', $roleName)->firstOrFail();

        return User::create([
            'username' => $username,
            'email' => "{$username}@test.local",
            'password' => Hash::make('password'),
            'role_id' => $role->id,
            'nama_lengkap' => $namaLengkap,
            'is_active' => true,
            'is_password_default' => false,
        ]);
    }
}
