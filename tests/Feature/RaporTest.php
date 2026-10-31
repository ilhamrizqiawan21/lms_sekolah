<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\NilaiAkhir;
use App\Models\RaporNilai;
use App\Models\RaporSiswa;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Models\WaliKelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaporTest extends TestCase
{
    use RefreshDatabase;

    public function test_wali_kelas_dapat_membuat_dan_memfinalkan_rapor_dengan_snapshot_nilai(): void
    {
        [$guru, $waliKelas, $kelasMapel, $siswa] = $this->fixture();
        Absensi::create(['siswa_id' => $siswa->id, 'kelas_mapel_id' => $kelasMapel->id, 'tanggal' => '2026-08-01', 'status' => 'izin']);
        NilaiAkhir::create([
            'siswa_id' => $siswa->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'tahun_ajaran_id' => $waliKelas->tahun_ajaran_id,
            'semester' => '1',
            'sum1' => 80,
            'sts' => 90,
        ]);

        $this->actingAs($guru)
            ->post(route('guru.wali-kelas.rapor.generate', $waliKelas), ['semester' => '1'])
            ->assertRedirect();

        $rapor = RaporSiswa::firstOrFail();
        $detail = RaporNilai::firstOrFail();
        $this->assertSame('draft', $rapor->status);
        $this->assertSame(1, $rapor->izin);
        $this->assertSame(85.0, (float) $detail->nilai_akhir);
        $this->assertSame('B (Baik)', $detail->predikat);

        $this->actingAs($guru)
            ->get(route('guru.wali-kelas.rapor', [$waliKelas, 'semester' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/WaliKelas/Rapor')
                ->has('students', 1)
                ->where('students.0.rapor.status', 'draft')
                ->where('students.0.rapor.nilai.0.nilai_akhir', 85)
            );

        $this->actingAs($guru)
            ->put(route('guru.wali-kelas.rapor.update', [$waliKelas, $rapor]), [
                'catatan_wali_kelas' => 'Pertahankan semangat belajar.',
                'status' => 'final',
                'nilai' => [$detail->id => ['deskripsi' => 'Menguasai materi dengan baik.']],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rapor_siswa', ['id' => $rapor->id, 'status' => 'final', 'catatan_wali_kelas' => 'Pertahankan semangat belajar.']);
        $this->assertDatabaseHas('rapor_nilai', ['id' => $detail->id, 'deskripsi' => 'Menguasai materi dengan baik.']);

        $this->actingAs($guru)
            ->get(route('guru.wali-kelas.rapor.print', [$waliKelas, $rapor]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/WaliKelas/RaporCetak')
                ->where('rapor.siswa.nis', 'S-001')
                ->where('rapor.status', 'final')
            );
    }

    public function test_rapor_final_tidak_dapat_diubah_dan_wali_kelas_lain_tidak_dapat_mengakses(): void
    {
        [$guru, $waliKelas] = $this->fixture();
        $rapor = RaporSiswa::create([
            'wali_kelas_id' => $waliKelas->id,
            'siswa_id' => Siswa::where('kelas_id', $waliKelas->kelas_id)->firstOrFail()->id,
            'tahun_ajaran_id' => $waliKelas->tahun_ajaran_id,
            'semester' => '1',
            'status' => 'final',
        ]);
        $guruLain = $this->user('guru-lain');

        $this->actingAs($guru)
            ->from(route('guru.wali-kelas.rapor', $waliKelas))
            ->put(route('guru.wali-kelas.rapor.update', [$waliKelas, $rapor]), [
                'status' => 'draft',
                'nilai' => ['0' => ['deskripsi' => '']],
            ])
            ->assertSessionHasErrors('rapor');

        $this->actingAs($guruLain)
            ->get(route('guru.wali-kelas.rapor', $waliKelas))
            ->assertForbidden();
    }

    public function test_siswa_hanya_melihat_rapor_yang_sudah_final(): void
    {
        [$guru, $waliKelas, , $siswa] = $this->fixture();
        $studentUser = $siswa->user;
        RaporSiswa::create([
            'wali_kelas_id' => $waliKelas->id,
            'siswa_id' => $siswa->id,
            'tahun_ajaran_id' => $waliKelas->tahun_ajaran_id,
            'semester' => '1',
            'status' => 'draft',
        ]);

        $this->actingAs($studentUser)
            ->get(route('siswa.rapor.index', ['semester' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Siswa/Rapor')->where('rapor', null));

        RaporSiswa::query()->update(['status' => 'final']);
        $this->actingAs($studentUser)
            ->get(route('siswa.rapor.index', ['semester' => '1']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Siswa/Rapor')->where('rapor.nama', 'Siswa'));
    }

    private function fixture(): array
    {
        $guru = $this->user('guru');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'Rapor']);
        $mapel = MataPelajaran::create(['kode' => 'RAP', 'nama_mapel' => 'Bahasa Rapor', 'urutan' => 1]);
        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id, 'mapel_id' => $mapel->id, 'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id, 'semester' => '1',
        ]);
        $waliKelas = WaliKelas::create([
            'kelas_id' => $kelas->id, 'guru_id' => $guru->id, 'tahun_ajaran_id' => $tahunAjaran->id,
        ]);
        $siswaUser = $this->user('siswa');
        $siswa = Siswa::create(['user_id' => $siswaUser->id, 'nis' => 'S-001', 'kelas_id' => $kelas->id, 'status' => 'aktif']);

        return [$guru, $waliKelas, $kelasMapel, $siswa];
    }

    private function user(string $name): User
    {
        $role = Role::firstOrCreate(['nama_role' => str_starts_with($name, 'guru') ? 'guru' : 'siswa']);

        return User::create([
            'username' => $name.'-'.uniqid(), 'email' => $name.'-'.uniqid().'@test.local', 'password' => 'password',
            'nama_lengkap' => ucfirst($name), 'role_id' => $role->id, 'is_active' => true,
        ]);
    }
}
