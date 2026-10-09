<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Pengaturan;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AbsensiExportAllTest extends TestCase
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

    private function seedAbsensiFixture(User $guru): array
    {
        Pengaturan::query()->updateOrCreate(['key' => 'semester_aktif'], ['value' => '1']);
        $taAktif = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'MTK', 'nama_mapel' => 'Matematika', 'urutan' => 1]);

        $kelasList = [
            Kelas::create(['tingkat' => '7', 'nama_kelas' => 'A']),
            Kelas::create(['tingkat' => '7', 'nama_kelas' => 'B']),
        ];

        $kelasMapelList = [];
        foreach ($kelasList as $kelas) {
            $kelasMapel = KelasMapel::create([
                'kelas_id' => $kelas->id,
                'mapel_id' => $mapel->id,
                'guru_id' => $guru->id,
                'tahun_ajaran_id' => $taAktif->id,
                'semester' => '1',
                'pertemuan_per_minggu' => 2,
            ]);
            $kelasMapelList[] = $kelasMapel;

            $siswaUser = $this->makeUser("Siswa {$kelas->nama_kelas}", 'siswa');
            $siswa = Siswa::create([
                'user_id' => $siswaUser->id,
                'nis' => '2025'.$kelas->id,
                'kelas_id' => $kelas->id,
                'angkatan' => '2025',
                'status' => 'aktif',
                'tinggal_kelas' => false,
            ]);

            Absensi::create([
                'siswa_id' => $siswa->id,
                'kelas_mapel_id' => $kelasMapel->id,
                'tanggal' => date('Y-m-01'),
                'status' => 'hadir',
            ]);
        }

        return $kelasMapelList;
    }

    public function test_guru_can_export_daftar_absensi_for_all_classes_and_all_months(): void
    {
        $guru = $this->makeUser('Guru Export Test', 'guru');
        $this->seedAbsensiFixture($guru);

        $this->actingAs($guru)->get('/guru/absensi-export/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($guru)->get('/guru/absensi-export/excel')
            ->assertOk();
    }

    public function test_guru_can_export_rekap_absensi_for_all_classes_and_all_months(): void
    {
        $guru = $this->makeUser('Guru Rekap Export Test', 'guru');
        $this->seedAbsensiFixture($guru);

        $this->actingAs($guru)->get('/guru/rekap-absensi/export/pdf?mode=keseluruhan')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($guru)->get('/guru/rekap-absensi/export/excel?mode=keseluruhan')
            ->assertOk();
    }

    public function test_admin_can_export_absensi_for_all_classes_and_all_months(): void
    {
        $admin = $this->makeUser('Admin Export Test', 'admin');
        $guru = $this->makeUser('Guru Untuk Admin Test', 'guru');
        $this->seedAbsensiFixture($guru);

        $this->actingAs($admin)->get('/admin/export/absensi/pdf')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)->get('/admin/export/absensi/excel')
            ->assertOk();
    }

    public function test_guru_export_pdf_single_kelas_single_bulan_still_works(): void
    {
        $guru = $this->makeUser('Guru Single Export Test', 'guru');
        $kelasMapelList = $this->seedAbsensiFixture($guru);
        $kelasMapel = $kelasMapelList[0];

        $this->actingAs($guru)->get("/guru/absensi/{$kelasMapel->id}/export/pdf?bulan=".date('Y-m'))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
