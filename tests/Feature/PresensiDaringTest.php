<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\KelasDaring;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresensiDaringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.require_student_phone' => false]);
    }

    public function test_student_can_attend_daring_class_on_same_day(): void
    {
        $roleSiswa = Role::firstOrCreate(['nama_role' => 'siswa']);
        $userSiswa = User::create([
            'username' => 'siswa-test',
            'nama_lengkap' => 'Siswa Test',
            'password' => bcrypt('password'),
            'role_id' => $roleSiswa->id,
            'is_active' => true,
        ]);

        $tahun = TahunAjaran::create(['tahun' => '2025/2026', 'semester' => 'ganjil', 'is_active' => true]);
        $kelas = Kelas::create(['nama_kelas' => 'X-A', 'tingkat' => '10', 'tahun_ajaran_id' => $tahun->id]);
        $mapel = MataPelajaran::create(['kode_mapel' => 'MAT-10', 'nama_mapel' => 'Matematika']);

        $roleGuru = Role::firstOrCreate(['nama_role' => 'guru']);
        $userGuru = User::create([
            'username' => 'guru-test',
            'nama_lengkap' => 'Guru Test',
            'password' => bcrypt('password'),
            'role_id' => $roleGuru->id,
            'is_active' => true,
        ]);

        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $userGuru->id,
            'tahun_ajaran_id' => $tahun->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        $siswa = Siswa::create([
            'user_id' => $userSiswa->id,
            'kelas_id' => $kelas->id,
            'nis' => '12345',
            'status' => 'aktif',
        ]);

        $sesiDaring = KelasDaring::create([
            'kelas_mapel_id' => $kelasMapel->id,
            'guru_id' => $userGuru->id,
            'judul' => 'Sesi Aljabar Daring',
            'tanggal' => now()->toDateString(),
            'pelajaran_ke' => 1,
            'meeting_url' => 'https://meet.google.com/abc-def-ghi',
            'status' => KelasDaring::STATUS_TERJADWAL,
        ]);

        $response = $this->actingAs($userSiswa)
            ->post(route('siswa.kelas-daring.presensi', $sesiDaring));

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('absensi', [
            'siswa_id' => $siswa->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'status' => 'h',
            'is_daring' => true,
            'kelas_daring_id' => $sesiDaring->id,
        ]);
    }

    public function test_student_cannot_attend_daring_class_for_different_date(): void
    {
        $roleSiswa = Role::firstOrCreate(['nama_role' => 'siswa']);
        $userSiswa = User::create([
            'username' => 'siswa-test2',
            'nama_lengkap' => 'Siswa Test 2',
            'password' => bcrypt('password'),
            'role_id' => $roleSiswa->id,
            'is_active' => true,
        ]);

        $tahun = TahunAjaran::create(['tahun' => '2025/2026', 'semester' => 'ganjil', 'is_active' => true]);
        $kelas = Kelas::create(['nama_kelas' => 'X-B', 'tingkat' => '10', 'tahun_ajaran_id' => $tahun->id]);
        $mapel = MataPelajaran::create(['kode_mapel' => 'BIO-10', 'nama_mapel' => 'Biologi']);

        $roleGuru = Role::firstOrCreate(['nama_role' => 'guru']);
        $userGuru = User::create([
            'username' => 'guru-test2',
            'nama_lengkap' => 'Guru Test 2',
            'password' => bcrypt('password'),
            'role_id' => $roleGuru->id,
            'is_active' => true,
        ]);

        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $userGuru->id,
            'tahun_ajaran_id' => $tahun->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        $siswa = Siswa::create([
            'user_id' => $userSiswa->id,
            'kelas_id' => $kelas->id,
            'nis' => '12346',
            'status' => 'aktif',
        ]);

        $sesiDaring = KelasDaring::create([
            'kelas_mapel_id' => $kelasMapel->id,
            'guru_id' => $userGuru->id,
            'judul' => 'Sesi Biologi Sel',
            'tanggal' => now()->addDays(2)->toDateString(),
            'pelajaran_ke' => 1,
            'meeting_url' => 'https://meet.google.com/abc-xyz-ghi',
            'status' => KelasDaring::STATUS_TERJADWAL,
        ]);

        $response = $this->actingAs($userSiswa)
            ->post(route('siswa.kelas-daring.presensi', $sesiDaring));

        $response->assertSessionHas('error');

        $this->assertDatabaseMissing('absensi', [
            'siswa_id' => $siswa->id,
            'kelas_daring_id' => $sesiDaring->id,
        ]);
    }
}
