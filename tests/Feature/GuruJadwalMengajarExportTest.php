<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruJadwalMengajarExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role): User
    {
        $roleModel = Role::firstOrCreate(['nama_role' => $role]);

        return User::create([
            'username' => $role.'-'.uniqid(),
            'email' => $role.'-'.uniqid().'@example.test',
            'password' => Hash::make('secret'),
            'is_password_default' => false,
            'nama_lengkap' => ucfirst($role).' Uji',
            'is_active' => true,
            'role_id' => $roleModel->id,
        ]);
    }

    public function test_guru_can_export_schedule_pdf(): void
    {
        Pengaturan::query()->updateOrCreate(['key' => 'semester_aktif'], ['value' => '1']);
        $ta = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $guru = $this->makeUser('guru');
        $kelas = Kelas::create(['tingkat' => '7', 'nama_kelas' => 'A']);
        $mapel = MataPelajaran::create(['kode' => 'MTK', 'nama_mapel' => 'Matematika', 'urutan' => 1]);
        $km = KelasMapel::create([
            'kelas_id' => $kelas->id, 'mapel_id' => $mapel->id, 'guru_id' => $guru->id,
            'tahun_ajaran_id' => $ta->id, 'semester' => '1', 'pertemuan_per_minggu' => 2,
        ]);

        $this->actingAs($guru)->post(route('guru.jadwal-mengajar.store'), [
            'kelas_mapel_id' => $km->id, 'hari' => 1, 'pelajaran_ke' => 1,
        ])->assertSessionHasNoErrors();

        $response = $this->actingAs($guru)->get(route('guru.jadwal-mengajar.export.pdf'));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_non_guru_cannot_export_schedule_pdf(): void
    {
        $siswa = $this->makeUser('siswa');

        $this->actingAs($siswa)->get(route('guru.jadwal-mengajar.export.pdf'))->assertForbidden();
        auth()->logout();
        $this->get(route('guru.jadwal-mengajar.export.pdf'))->assertRedirect();
    }
}
