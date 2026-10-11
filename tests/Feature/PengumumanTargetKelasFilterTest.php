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

    public function test_guru_cannot_update_or_delete_another_guru_announcement(): void
    {
        Role::create(['nama_role' => 'admin']);
        Role::create(['nama_role' => 'guru']);

        $guruA = $this->createUser('guru-a-owner', 'Guru A Owner', 'guru');
        $guruB = $this->createUser('guru-b-outsider', 'Guru B Outsider', 'guru');

        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'C']);
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'OWN', 'nama_mapel' => 'Ownership Test', 'urutan' => 1]);
        KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guruA->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        $pengumuman = Pengumuman::create([
            'judul' => 'Milik Guru A',
            'isi' => 'isi',
            'target' => 'kelas_mapel',
            'target_kelas' => json_encode([(string) $kelas->id]),
            'created_by' => $guruA->id,
        ]);

        $this->actingAs($guruB)
            ->put(route('guru.pengumuman.update', $pengumuman), [
                'judul' => 'Diubah paksa', 'isi' => 'isi', 'target' => 'kelas_mapel',
                'target_kelas_ids' => [$kelas->id],
            ])
            ->assertForbidden();

        $this->actingAs($guruB)
            ->delete(route('guru.pengumuman.destroy', $pengumuman))
            ->assertForbidden();

        $this->assertDatabaseHas('pengumuman', ['id' => $pengumuman->id, 'judul' => 'Milik Guru A']);
    }

    public function test_guru_cannot_view_an_announcement_targeting_a_class_they_do_not_teach(): void
    {
        Role::create(['nama_role' => 'admin']);
        Role::create(['nama_role' => 'guru']);

        $admin = $this->createUser('admin-view-deny', 'Admin View Deny', 'admin');
        $guru = $this->createUser('guru-view-deny', 'Guru View Deny', 'guru');
        $kelasLain = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'Z']);

        $pengumuman = Pengumuman::create([
            'judul' => 'Bukan Untuk Guru Ini',
            'isi' => 'isi',
            'target' => 'kelas_mapel',
            'target_kelas' => json_encode([(string) $kelasLain->id]),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($guru)
            ->get(route('guru.pengumuman.show', $pengumuman))
            ->assertForbidden();
    }

    public function test_admin_and_guru_can_target_all_classes_and_show_on_login(): void
    {
        Role::create(['nama_role' => 'admin']);
        Role::create(['nama_role' => 'guru']);

        $admin = $this->createUser('admin-semua-kelas', 'Admin Semua Kelas', 'admin');
        $guru = $this->createUser('guru-semua-kelas', 'Guru Semua Kelas', 'guru');
        $kelasA = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $kelasB = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'B']);
        $kelasLain = Kelas::create(['tingkat' => 'VIII', 'nama_kelas' => 'A']);
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'ALL', 'nama_mapel' => 'Semua Kelas', 'urutan' => 1]);
        foreach ([$kelasA, $kelasB] as $kelas) {
            KelasMapel::create(['kelas_id' => $kelas->id, 'mapel_id' => $mapel->id, 'guru_id' => $guru->id, 'tahun_ajaran_id' => $tahunAjaran->id, 'semester' => '1', 'pertemuan_per_minggu' => 2]);
        }

        $this->actingAs($admin)->post(route('admin.pengumuman.store'), [
            'judul' => 'Admin Semua Kelas', 'isi' => 'isi', 'target' => 'kelas_mapel',
            'target_kelas_ids' => [$kelasA->id, $kelasB->id, $kelasLain->id], 'is_public_login' => '1',
        ])->assertRedirect(route('admin.pengumuman.index'));

        $this->actingAs($guru)->post(route('guru.pengumuman.store'), [
            'judul' => 'Guru Semua Kelas', 'isi' => 'isi', 'target' => 'kelas_mapel',
            'target_kelas_ids' => [$kelasA->id, $kelasB->id], 'is_public_login' => '1',
        ])->assertRedirect(route('guru.pengumuman.index'));

        $this->actingAs($guru)->post(route('guru.pengumuman.store'), [
            'judul' => 'Guru Kelas Asing', 'isi' => 'isi', 'target' => 'kelas_mapel',
            'target_kelas_ids' => [$kelasA->id, $kelasLain->id],
        ])->assertForbidden();

        $this->assertSame([$kelasA->id, $kelasB->id, $kelasLain->id], Pengumuman::where('judul', 'Admin Semua Kelas')->firstOrFail()->targetKelasIds());
        $this->assertSame([$kelasA->id, $kelasB->id], Pengumuman::where('judul', 'Guru Semua Kelas')->firstOrFail()->targetKelasIds());
        $this->assertDatabaseMissing('pengumuman', ['judul' => 'Guru Kelas Asing']);

        auth()->logout();
        $this->get(route('login'))->assertOk()->assertInertia(fn ($page) => $page
            ->has('publicAnnouncements', 2));
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
