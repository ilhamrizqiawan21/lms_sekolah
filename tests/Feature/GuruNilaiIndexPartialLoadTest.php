<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class GuruNilaiIndexPartialLoadTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_only_builds_the_selected_class_group(): void
    {
        [$guru, $kelasMapelA, $kelasMapelB] = $this->fixture();

        $this->actingAs($guru)
            ->get(route('guru.nilai.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/Nilai/Index')
                ->has('kelasMapel', 2)
                ->has('groups', 1)
                ->where('groups.0.kelas_mapel_id', $kelasMapelA->id)
            );

        $this->actingAs($guru)
            ->get(route('guru.nilai.index', ['kelas_mapel_id' => $kelasMapelB->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/Nilai/Index')
                ->has('groups', 1)
                ->where('groups.0.kelas_mapel_id', $kelasMapelB->id)
            );
    }

    public function test_index_ignores_a_class_id_the_teacher_does_not_own(): void
    {
        [$guru, $kelasMapelA, , $foreignKelasMapel] = $this->fixture();

        $this->actingAs($guru)
            ->get(route('guru.nilai.index', ['kelas_mapel_id' => $foreignKelasMapel->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/Nilai/Index')
                ->has('groups', 1)
                ->where('groups.0.kelas_mapel_id', $kelasMapelA->id)
            );
    }

    public function test_bulk_store_redirect_preserves_the_selected_class(): void
    {
        [$guru, $kelasMapelA, $kelasMapelB] = $this->fixture();

        $this->actingAs($guru)
            ->post(route('guru.nilai.store.bulk'), [
                'semester' => '1',
                'kelas_mapel_ids' => [$kelasMapelB->id],
                'nilai' => [$kelasMapelB->id => []],
            ])
            ->assertRedirect(route('guru.nilai.index', ['kelas_mapel_id' => $kelasMapelB->id]));
    }

    /**
     * @return array{0: User, 1: KelasMapel, 2: KelasMapel, 3: KelasMapel}
     */
    private function fixture(): array
    {
        Role::create(['nama_role' => 'admin']);
        Role::create(['nama_role' => 'guru']);
        Role::create(['nama_role' => 'siswa']);
        Role::create(['nama_role' => 'kepala_sekolah']);

        $guru = $this->createUser('guru-nilai-partial', 'Guru Nilai Partial', 'guru');
        $guruLain = $this->createUser('guru-nilai-lain', 'Guru Nilai Lain', 'guru');

        $kelasA = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $kelasB = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'B']);
        $kelasC = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'C']);
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);

        $mapel = MataPelajaran::create(['kode' => 'NIL', 'nama_mapel' => 'Nilai Partial Test', 'urutan' => 1]);

        $kelasMapelA = KelasMapel::create([
            'kelas_id' => $kelasA->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        $kelasMapelB = KelasMapel::create([
            'kelas_id' => $kelasB->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        $foreignKelasMapel = KelasMapel::create([
            'kelas_id' => $kelasC->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guruLain->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        return [$guru, $kelasMapelA, $kelasMapelB, $foreignKelasMapel];
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
