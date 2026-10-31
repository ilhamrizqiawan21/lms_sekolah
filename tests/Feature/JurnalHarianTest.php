<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\JurnalHarian;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\Kurikulum;
use App\Models\MataPelajaran;
use App\Models\RencanaPertemuan;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JurnalHarianTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_dapat_membuat_jurnal_dari_promes_dan_absensi_direkap(): void
    {
        [$guru, $kelasMapel, $rencana] = $this->fixture();
        $siswaPertama = $this->student($kelasMapel->kelas_id, 'S-001');
        $siswaKedua = $this->student($kelasMapel->kelas_id, 'S-002');
        Absensi::create(['siswa_id' => $siswaPertama->id, 'kelas_mapel_id' => $kelasMapel->id, 'tanggal' => '2026-07-13', 'status' => 'hadir']);
        Absensi::create(['siswa_id' => $siswaKedua->id, 'kelas_mapel_id' => $kelasMapel->id, 'tanggal' => '2026-07-13', 'status' => 'sakit']);

        $this->actingAs($guru)->post(route('guru.jurnal-harian.store'), [
            'kelas_mapel_id' => $kelasMapel->id,
            'rencana_pertemuan_id' => $rencana->id,
            'tanggal' => '2026-07-20',
            'pertemuan_ke' => 99,
            'jp' => 40,
            'materi_pokok' => 'Materi tambahan',
            'tujuan_pembelajaran' => '',
            'status' => 'final',
        ])->assertRedirect();

        $jurnal = JurnalHarian::firstOrFail();
        $this->assertSame('2026-07-13', $jurnal->tanggal->toDateString());
        $this->assertSame(1, $jurnal->pertemuan_ke);
        $this->assertSame(2, $jurnal->jp);
        $this->assertSame(2, $jurnal->jumlah_siswa);
        $this->assertSame(RencanaPertemuan::STATUS_TERLAKSANA, $rencana->fresh()->status);
        $this->assertSame('2026-07-13', $rencana->fresh()->tanggal_realisasi->toDateString());

        $this->actingAs($guru)
            ->get(route('guru.jurnal-harian.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/JurnalHarian/Index')
                ->has('jurnal', 1)
                ->where('jurnal.0.attendance.hadir', 1)
                ->where('jurnal.0.attendance.sakit', 1)
                ->where('jurnal.0.jumlah_siswa', 2)
            );
    }

    public function test_jurnal_menghormati_scope_guru_dan_mencegah_duplikasi_tanggal(): void
    {
        [$guru, $kelasMapel, $rencana] = $this->fixture();
        $guruLain = $this->user('guru-lain');
        $jurnal = JurnalHarian::create([
            'guru_id' => $guru->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'tahun_ajaran_id' => $kelasMapel->tahun_ajaran_id,
            'tanggal' => '2026-07-13',
            'pertemuan_ke' => 1,
            'jp' => 2,
            'status' => 'draft',
        ]);

        $this->actingAs($guru)
            ->from(route('guru.jurnal-harian.index'))
            ->post(route('guru.jurnal-harian.store'), [
                'kelas_mapel_id' => $kelasMapel->id,
                'tanggal' => '2026-07-13',
                'pertemuan_ke' => 2,
                'jp' => 2,
                'status' => 'draft',
            ])
            ->assertSessionHasErrors('tanggal');

        $this->actingAs($guruLain)
            ->put(route('guru.jurnal-harian.update', $jurnal), [
                'tanggal' => '2026-07-14',
                'pertemuan_ke' => 1,
                'jp' => 2,
                'status' => 'final',
            ])
            ->assertForbidden();

        $this->actingAs($guruLain)
            ->delete(route('guru.jurnal-harian.destroy', $jurnal))
            ->assertForbidden();

        $this->assertDatabaseHas('jurnal_harian', ['id' => $jurnal->id, 'status' => 'draft']);
        $this->assertNotNull($rencana);
    }

    public function test_jurnal_final_tidak_dapat_dihapus_dan_draft_dapat_dihapus(): void
    {
        [$guru, $kelasMapel] = $this->fixture();
        $final = JurnalHarian::create([
            'guru_id' => $guru->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'tahun_ajaran_id' => $kelasMapel->tahun_ajaran_id,
            'tanggal' => '2026-07-13',
            'pertemuan_ke' => 1,
            'jp' => 2,
            'status' => 'final',
        ]);

        $this->actingAs($guru)
            ->from(route('guru.jurnal-harian.index'))
            ->delete(route('guru.jurnal-harian.destroy', $final))
            ->assertSessionHasErrors('jurnal');
        $this->assertDatabaseHas('jurnal_harian', ['id' => $final->id]);

        $final->update(['status' => 'draft']);
        $this->actingAs($guru)->delete(route('guru.jurnal-harian.destroy', $final))->assertRedirect();
        $this->assertDatabaseMissing('jurnal_harian', ['id' => $final->id]);
    }

    private function fixture(): array
    {
        $guru = $this->user('guru');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'JRN', 'nama_mapel' => 'Mapel Jurnal', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'Jurnal']);
        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id, 'mapel_id' => $mapel->id, 'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id, 'semester' => '1',
        ]);
        $kurikulum = Kurikulum::create([
            'tahun_ajaran_id' => $tahunAjaran->id, 'mapel_id' => $mapel->id, 'guru_id' => $guru->id,
            'semester' => '1', 'nama' => 'ATP Jurnal',
        ]);
        $kurikulum->kelasMapel()->attach($kelasMapel);
        $tp = TujuanPembelajaran::create([
            'kurikulum_id' => $kurikulum->id, 'kode' => 'TP-1', 'urutan' => 1,
            'tujuan' => 'Tujuan jurnal', 'materi_pokok' => 'Materi jurnal', 'alokasi_jp' => 2,
        ]);
        $rencana = RencanaPertemuan::create([
            'kurikulum_id' => $kurikulum->id, 'kelas_mapel_id' => $kelasMapel->id,
            'tujuan_pembelajaran_id' => $tp->id, 'pertemuan_ke' => 1, 'tanggal_rencana' => '2026-07-13',
            'jp' => 2, 'status' => RencanaPertemuan::STATUS_DIRENCANAKAN,
        ]);

        return [$guru, $kelasMapel, $rencana];
    }

    private function student(int $kelasId, string $nis): Siswa
    {
        $user = $this->user($nis);

        return Siswa::create(['user_id' => $user->id, 'nis' => $nis, 'kelas_id' => $kelasId, 'status' => 'aktif']);
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
