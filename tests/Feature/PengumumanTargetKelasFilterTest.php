<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Pengumuman;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class PengumumanTargetKelasFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_only_sees_announcements_targeting_their_own_class(): void
    {
        Role::create(['nama_role' => 'admin']);
        Role::create(['nama_role' => 'guru']);

        $admin = $this->createUser('admin-target-kelas', 'Admin Target Kelas', 'admin');
        $guru = $this->createUser('guru-target-kelas', 'Guru Target Kelas', 'guru');

        $kelasMilikGuru = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $kelasLain = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'B']);

        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'TGT', 'nama_mapel' => 'Target Kelas Test', 'urutan' => 1]);

        KelasMapel::create([
            'kelas_id' => $kelasMilikGuru->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        $matching = Pengumuman::create([
            'judul' => 'Untuk Kelas Guru',
            'isi' => 'isi',
            'target' => 'kelas_mapel',
            'target_kelas' => json_encode([(string) $kelasMilikGuru->id]),
            'created_by' => $admin->id,
        ]);

        $nonMatching = Pengumuman::create([
            'judul' => 'Untuk Kelas Lain',
            'isi' => 'isi',
            'target' => 'kelas_mapel',
            'target_kelas' => json_encode([(string) $kelasLain->id]),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($guru)
            ->get(route('guru.pengumuman.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Pengumuman/Index')
                ->has('pengumuman.data', 1)
                ->where('pengumuman.data.0.judul', $matching->judul)
            );

        $this->assertNotSame($matching->id, $nonMatching->id);
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
