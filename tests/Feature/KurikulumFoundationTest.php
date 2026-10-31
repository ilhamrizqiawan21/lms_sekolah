<?php

namespace Tests\Feature;

use App\Http\Requests\Guru\StoreKurikulumRequest;
use App\Models\CalendarEvent;
use App\Models\CapaianPembelajaran;
use App\Models\JadwalMengajar;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\Kurikulum;
use App\Models\MataPelajaran;
use App\Models\RencanaPertemuan;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class KurikulumFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_kurikulum_cp_tp_dan_kelas_mapel_saling_terhubung(): void
    {
        $guru = $this->createUser('guru');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'PAI', 'nama_mapel' => 'Pendidikan Agama Islam', 'urutan' => 1]);
        $kelasA = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $kelasB = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'B']);

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

        $kurikulum = Kurikulum::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'semester' => '1',
            'jenis_kurikulum' => 'merdeka',
            'fase' => 'D',
            'nama' => 'ATP PAI Fase D Semester 1',
            'status' => 'draft',
        ]);
        $kurikulum->kelasMapel()->attach([$kelasMapelA->id, $kelasMapelB->id]);

        $cp = CapaianPembelajaran::create([
            'kurikulum_id' => $kurikulum->id,
            'kode' => 'CP-1',
            'elemen' => 'Al-Qur’an',
            'deskripsi' => 'Peserta didik memahami kandungan ayat pilihan.',
            'urutan' => 1,
        ]);
        $tp = TujuanPembelajaran::create([
            'kurikulum_id' => $kurikulum->id,
            'capaian_pembelajaran_id' => $cp->id,
            'kode' => 'TP-1',
            'urutan' => 1,
            'tujuan' => 'Peserta didik mampu menjelaskan kandungan ayat.',
            'materi_pokok' => 'Ayat pilihan',
            'alokasi_jp' => 4,
            'estimasi_pertemuan' => 2,
            'bentuk_asesmen' => 'Tugas dan sumatif',
        ]);

        $kurikulum->load(['tahunAjaran', 'mataPelajaran', 'guru', 'capaianPembelajaran', 'tujuanPembelajaran', 'kelasMapel']);

        $this->assertSame('2026/2027', $kurikulum->tahunAjaran->tahun);
        $this->assertSame('Pendidikan Agama Islam', $kurikulum->mataPelajaran->nama_mapel);
        $this->assertSame($guru->id, $kurikulum->guru->id);
        $this->assertCount(1, $kurikulum->capaianPembelajaran);
        $this->assertSame($cp->id, $tp->capaianPembelajaran->id);
        $this->assertSame(['TP-1'], $kurikulum->tujuanPembelajaran->pluck('kode')->all());
        $this->assertCount(2, $kurikulum->kelasMapel);
        $this->assertSame(4, $tp->alokasi_jp);
    }

    public function test_kurikulum_policy_menghormati_pemilik_dan_penugasan_kelas_mapel(): void
    {
        $pemilik = $this->createUser('guru');
        $guruLain = $this->createUser('guru');
        $guruTidakTerkait = $this->createUser('guru');
        $admin = $this->createUser('admin');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['nama_mapel' => 'Matematika', 'kode' => 'MTK', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $kelasLain = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'B']);

        $kelasMapelPemilik = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $pemilik->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
        ]);
        $kelasMapelGuruLain = KelasMapel::create([
            'kelas_id' => $kelasLain->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guruLain->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
        ]);

        $kurikulum = Kurikulum::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $pemilik->id,
            'semester' => '1',
            'jenis_kurikulum' => 'merdeka',
            'nama' => 'ATP Matematika',
        ]);
        $kurikulum->kelasMapel()->attach([$kelasMapelPemilik->id, $kelasMapelGuruLain->id]);

        $this->assertTrue($pemilik->can('update', $kurikulum));
        $this->assertTrue($guruLain->can('view', $kurikulum));
        $this->assertFalse($guruLain->can('update', $kurikulum));
        $this->assertFalse($guruTidakTerkait->can('view', $kurikulum));
        $this->assertTrue($admin->can('delete', $kurikulum));
    }

    public function test_validasi_kurikulum_menolak_semester_dan_jenis_yang_tidak_dikenal(): void
    {
        $request = new StoreKurikulumRequest;
        $validator = Validator::make([
            'tahun_ajaran_id' => 1,
            'mapel_id' => 1,
            'semester' => '3',
            'jenis_kurikulum' => 'kurikulum-rahasia',
            'nama' => '',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('semester', $validator->errors()->toArray());
        $this->assertArrayHasKey('jenis_kurikulum', $validator->errors()->toArray());
        $this->assertArrayHasKey('nama', $validator->errors()->toArray());
    }

    public function test_guru_dapat_mengelola_kurikulum_cp_dan_tp_melalui_route(): void
    {
        $guru = $this->createUser('guru');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'IPA', 'nama_mapel' => 'Ilmu Pengetahuan Alam', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        $response = $this->actingAs($guru)->post(route('guru.kurikulum.store'), [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'mapel_id' => $mapel->id,
            'semester' => '1',
            'kelas_mapel_ids' => [$kelasMapel->id],
            'jenis_kurikulum' => 'merdeka',
            'fase' => 'D',
            'nama' => 'ATP IPA Fase D',
        ]);

        $kurikulum = Kurikulum::firstOrFail();
        $response->assertRedirect(route('guru.kurikulum.show', $kurikulum));
        $this->assertDatabaseHas('kurikulum_kelas_mapel', [
            'kurikulum_id' => $kurikulum->id,
            'kelas_mapel_id' => $kelasMapel->id,
        ]);

        $this->actingAs($guru)
            ->get(route('guru.kurikulum.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/Kurikulum/Index')
                ->has('kurikulum', 1)
                ->has('kelasMapel', 1)
            );

        $this->actingAs($guru)
            ->get(route('guru.kurikulum.show', $kurikulum))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/Kurikulum/Show')
                ->where('kurikulum.nama', 'ATP IPA Fase D')
                ->has('kurikulum.capaian_pembelajaran', 0)
                ->has('kurikulum.tujuan_pembelajaran', 0)
            );

        $this->actingAs($guru)->post(route('guru.kurikulum.cp.store', $kurikulum), [
            'kode' => 'CP-1',
            'elemen' => 'Makhluk hidup',
            'deskripsi' => 'Peserta didik memahami ciri-ciri makhluk hidup.',
            'urutan' => 1,
        ])->assertRedirect();

        $cp = CapaianPembelajaran::firstOrFail();
        $this->actingAs($guru)->post(route('guru.kurikulum.tp.store', $kurikulum), [
            'capaian_pembelajaran_id' => $cp->id,
            'kode' => 'TP-1',
            'urutan' => 1,
            'tujuan' => 'Peserta didik mampu mengidentifikasi ciri makhluk hidup.',
            'materi_pokok' => 'Ciri-ciri makhluk hidup',
            'alokasi_jp' => 4,
            'estimasi_pertemuan' => 2,
            'bentuk_asesmen' => 'Tugas',
        ])->assertRedirect();

        $this->assertDatabaseHas('tujuan_pembelajaran', [
            'kurikulum_id' => $kurikulum->id,
            'capaian_pembelajaran_id' => $cp->id,
            'kode' => 'TP-1',
        ]);

        $this->actingAs($guru)->put(route('guru.kurikulum.cp.update', [$kurikulum, $cp]), [
            'kode' => 'CP-1A',
            'elemen' => 'Makhluk hidup',
            'deskripsi' => 'Deskripsi CP diperbarui.',
            'urutan' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('capaian_pembelajaran', [
            'id' => $cp->id,
            'kode' => 'CP-1A',
            'urutan' => 2,
        ]);
    }

    public function test_kurikulum_menolak_gabungan_kelas_mapel_lintas_scope(): void
    {
        $guru = $this->createUser('guru');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapelA = MataPelajaran::create(['kode' => 'MTK', 'nama_mapel' => 'Matematika', 'urutan' => 1]);
        $mapelB = MataPelajaran::create(['kode' => 'IPA', 'nama_mapel' => 'IPA', 'urutan' => 2]);
        $kelasA = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $kelasB = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'B']);
        $kelasMapelA = KelasMapel::create([
            'kelas_id' => $kelasA->id,
            'mapel_id' => $mapelA->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
        ]);
        $kelasMapelB = KelasMapel::create([
            'kelas_id' => $kelasB->id,
            'mapel_id' => $mapelB->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
        ]);

        $this->actingAs($guru)
            ->from(route('guru.kurikulum.index'))
            ->post(route('guru.kurikulum.store'), [
                'tahun_ajaran_id' => $tahunAjaran->id,
                'mapel_id' => $mapelA->id,
                'semester' => '1',
                'kelas_mapel_ids' => [$kelasMapelA->id, $kelasMapelB->id],
                'jenis_kurikulum' => 'merdeka',
                'nama' => 'ATP tidak valid',
            ])
            ->assertSessionHasErrors('kelas_mapel_ids');

        $this->assertDatabaseMissing('kurikulum', ['nama' => 'ATP tidak valid']);
    }

    public function test_nested_cp_dari_kurikulum_lain_tidak_dapat_diubah(): void
    {
        $guru = $this->createUser('guru');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'IPS', 'nama_mapel' => 'IPS', 'urutan' => 1]);
        $kelasA = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $kelasB = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'B']);
        $kelasMapelA = KelasMapel::create([
            'kelas_id' => $kelasA->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
        ]);
        $kelasMapelB = KelasMapel::create([
            'kelas_id' => $kelasB->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
        ]);
        $kurikulumA = Kurikulum::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'semester' => '1',
            'nama' => 'ATP A',
        ]);
        $kurikulumB = Kurikulum::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'semester' => '1',
            'nama' => 'ATP B',
        ]);
        $kurikulumA->kelasMapel()->attach($kelasMapelA);
        $kurikulumB->kelasMapel()->attach($kelasMapelB);
        $cpB = CapaianPembelajaran::create([
            'kurikulum_id' => $kurikulumB->id,
            'deskripsi' => 'CP milik kurikulum B',
            'urutan' => 1,
        ]);

        $this->actingAs($guru)
            ->put(route('guru.kurikulum.cp.update', [$kurikulumA, $cpB]), [
                'deskripsi' => 'Percobaan IDOR',
                'urutan' => 1,
            ])
            ->assertNotFound();
    }

    public function test_promes_mengalokasikan_tp_ke_tanggal_jadwal_dan_melewati_hari_libur(): void
    {
        [$guru, $kurikulum, $kelasMapel] = $this->promesFixture(4);
        JadwalMengajar::create([
            'guru_id' => $guru->id,
            'kelas_id' => $kelasMapel->kelas_id,
            'kelas_mapel_id' => $kelasMapel->id,
            'hari' => 1,
            'pelajaran_ke' => 1,
        ]);
        CalendarEvent::create([
            'title' => 'Libur sekolah',
            'event_date' => '2026-07-20',
            'is_holiday' => true,
            'scope' => 'school',
            'is_done' => false,
        ]);

        $response = $this->actingAs($guru)->post(route('guru.kurikulum.promes.generate', $kurikulum), [
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-27',
            'jp_per_pertemuan' => 2,
            'kelas_mapel_ids' => [$kelasMapel->id],
        ]);

        $response->assertRedirect();
        $this->assertSame(2, RencanaPertemuan::count());
        $this->assertSame(['2026-07-13', '2026-07-27'], RencanaPertemuan::orderBy('tanggal_rencana')->pluck('tanggal_rencana')->map->format('Y-m-d')->all());
        $this->assertSame(4, RencanaPertemuan::sum('jp'));

        $this->actingAs($guru)
            ->get(route('guru.kurikulum.promes.index', $kurikulum))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Guru/Kurikulum/Promes')
                ->where('summary.total', 2)
                ->where('summary.jp', 4)
                ->has('rencana', 2)
            );
    }

    public function test_promes_mencegah_regenerasi_rencana_yang_sudah_terlaksana(): void
    {
        [$guru, $kurikulum, $kelasMapel] = $this->promesFixture(2);
        JadwalMengajar::create([
            'guru_id' => $guru->id,
            'kelas_id' => $kelasMapel->kelas_id,
            'kelas_mapel_id' => $kelasMapel->id,
            'hari' => 1,
            'pelajaran_ke' => 1,
        ]);

        $this->actingAs($guru)->post(route('guru.kurikulum.promes.generate', $kurikulum), [
            'start_date' => '2026-07-13',
            'end_date' => '2026-07-13',
            'jp_per_pertemuan' => 2,
            'kelas_mapel_ids' => [$kelasMapel->id],
        ])->assertRedirect();

        $rencana = RencanaPertemuan::firstOrFail();
        $rencana->update(['status' => RencanaPertemuan::STATUS_TERLAKSANA]);

        $this->actingAs($guru)
            ->from(route('guru.kurikulum.promes.index', $kurikulum))
            ->post(route('guru.kurikulum.promes.generate', $kurikulum), [
                'start_date' => '2026-07-20',
                'end_date' => '2026-07-20',
                'jp_per_pertemuan' => 2,
                'kelas_mapel_ids' => [$kelasMapel->id],
                'replace' => true,
            ])
            ->assertSessionHasErrors('rencana');

        $this->actingAs($guru)
            ->delete(route('guru.kurikulum.promes.destroy', [$kurikulum, $rencana]))
            ->assertSessionHasErrors('rencana');
    }

    /**
     * @return array{0: User, 1: Kurikulum, 2: KelasMapel}
     */
    private function promesFixture(int $alokasiJp): array
    {
        $guru = $this->createUser('guru');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'PROMES', 'nama_mapel' => 'Mapel Promes', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'Promes']);
        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
        ]);
        $kurikulum = Kurikulum::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'semester' => '1',
            'nama' => 'ATP Promes',
        ]);
        $kurikulum->kelasMapel()->attach($kelasMapel);
        TujuanPembelajaran::create([
            'kurikulum_id' => $kurikulum->id,
            'kode' => 'TP-1',
            'urutan' => 1,
            'tujuan' => 'Tujuan Promes',
            'alokasi_jp' => $alokasiJp,
            'estimasi_pertemuan' => 2,
        ]);

        return [$guru, $kurikulum, $kelasMapel];
    }

    private function createUser(string $role): User
    {
        $roleModel = Role::firstOrCreate(['nama_role' => $role]);

        return User::create([
            'username' => $role.'-'.uniqid(),
            'email' => $role.'-'.uniqid().'@test.local',
            'password' => 'password',
            'nama_lengkap' => ucfirst($role),
            'role_id' => $roleModel->id,
            'is_active' => true,
        ]);
    }
}
