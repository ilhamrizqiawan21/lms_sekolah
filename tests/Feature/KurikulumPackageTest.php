<?php

namespace Tests\Feature;

use App\Models\CapaianPembelajaran;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\Kurikulum;
use App\Models\MataPelajaran;
use App\Models\RencanaPertemuan;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\TujuanPembelajaran;
use App\Models\User;
use App\Services\KurikulumPackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use ZipArchive;

class KurikulumPackageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_dapat_mengunduh_paket_administrasi_zip_berisi_docx(): void
    {
        [$guru, $kurikulum, $kelasMapel] = $this->fixture();
        $package = app(KurikulumPackageService::class)->create($kurikulum, $kelasMapel);

        $this->assertFileExists($package['path']);
        $zip = new ZipArchive;
        $this->assertSame(true, $zip->open($package['path']));
        $this->assertSame(8, $zip->count());
        $names = [];
        for ($index = 0; $index < $zip->count(); $index++) {
            $name = $zip->getNameIndex($index);
            $names[] = $name;
            $docxPath = tempnam(sys_get_temp_dir(), 'lms-test-docx-');
            file_put_contents($docxPath, $zip->getFromIndex($index));
            $docx = new ZipArchive;
            $this->assertSame(true, $docx->open($docxPath));
            $this->assertNotFalse($docx->getFromName('word/document.xml'));
            $this->assertStringContainsString('Bahasa Indonesia', (string) $docx->getFromName('word/document.xml'));
            $docx->close();
            @unlink($docxPath);
        }
        $this->assertContains('01 Capaian Pembelajaran Bahasa Indonesia.docx', $names);
        $this->assertContains('05 Program Semester - Ganjil Bahasa Indonesia.docx', $names);
        $zip->close();
        @unlink($package['path']);

        $this->actingAs($guru)
            ->get(route('guru.kurikulum.package.download', [$kurikulum, 'kelas_mapel_id' => $kelasMapel->id]))
            ->assertDownload($package['filename']);
    }

    public function test_paket_dapat_menggabungkan_banyak_kelas_dengan_docx_pdf_dan_modul_ajar(): void
    {
        [$guru, $kurikulum, $kelasMapel] = $this->fixture();
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'B']);
        $kelasMapelKedua = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $kelasMapel->mapel_id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $kelasMapel->tahun_ajaran_id,
            'semester' => '1',
        ]);
        $kurikulum->kelasMapel()->attach($kelasMapelKedua);

        $package = app(KurikulumPackageService::class)->create(
            $kurikulum,
            new Collection([$kelasMapel, $kelasMapelKedua]),
            'both',
        );

        $zip = new ZipArchive;
        $this->assertSame(true, $zip->open($package['path']));
        $this->assertSame(32, $zip->count());
        $names = [];
        for ($index = 0; $index < $zip->count(); $index++) {
            $names[] = $zip->getNameIndex($index);
        }
        $this->assertTrue(collect($names)->contains(fn (string $name) => str_ends_with($name, '10 Modul Ajar - Ganjil Bahasa Indonesia.docx')));
        $this->assertTrue(collect($names)->contains(fn (string $name) => str_ends_with($name, '10 Modul Ajar - Ganjil Bahasa Indonesia.pdf')));
        $pdfName = collect($names)->first(fn (string $name) => str_ends_with($name, '.pdf'));
        $this->assertNotNull($pdfName);
        $this->assertStringStartsWith('%PDF', (string) $zip->getFromName($pdfName));
        $zip->close();
        @unlink($package['path']);

        $url = route('guru.kurikulum.package.download', $kurikulum).'?'.http_build_query([
            'kelas_mapel_ids' => [$kelasMapel->id, $kelasMapelKedua->id],
            'format' => 'both',
        ]);
        $this->actingAs($guru)->get($url)->assertDownload($package['filename']);
    }

    public function test_paket_menolak_kelas_yang_tidak_terhubung_dan_guru_tidak_berwenang(): void
    {
        [$guru, $kurikulum, $kelasMapel] = $this->fixture();
        $kelasLain = Kelas::create(['tingkat' => 'VIII', 'nama_kelas' => 'Lain']);
        $mapelLain = MataPelajaran::create(['kode' => 'LAIN', 'nama_mapel' => 'Mapel Lain', 'urutan' => 2]);
        $kelasMapelLain = KelasMapel::create([
            'kelas_id' => $kelasLain->id,
            'mapel_id' => $mapelLain->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $kelasMapel->tahun_ajaran_id,
            'semester' => '1',
        ]);
        $guruLain = $this->user('guru-lain');

        $this->actingAs($guru)
            ->from(route('guru.kurikulum.show', $kurikulum))
            ->get(route('guru.kurikulum.package.download', [$kurikulum, 'kelas_mapel_id' => $kelasMapelLain->id]))
            ->assertNotFound();

        $this->actingAs($guruLain)
            ->get(route('guru.kurikulum.package.download', [$kurikulum, 'kelas_mapel_id' => $kelasMapel->id]))
            ->assertForbidden();
    }

    private function fixture(): array
    {
        $guru = $this->user('guru');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'BIN', 'nama_mapel' => 'Bahasa Indonesia', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
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
            'jenis_kurikulum' => 'merdeka',
            'nama' => 'ATP Bahasa Indonesia',
        ]);
        $kurikulum->kelasMapel()->attach($kelasMapel);
        $cp = CapaianPembelajaran::create([
            'kurikulum_id' => $kurikulum->id,
            'kode' => 'CP-1',
            'elemen' => 'Membaca',
            'deskripsi' => 'Memahami informasi dalam teks.',
            'urutan' => 1,
        ]);
        $tp = TujuanPembelajaran::create([
            'kurikulum_id' => $kurikulum->id,
            'capaian_pembelajaran_id' => $cp->id,
            'kode' => 'TP-1',
            'tujuan' => 'Siswa memahami teks.',
            'materi_pokok' => 'Teks eksposisi',
            'alokasi_jp' => 4,
            'estimasi_pertemuan' => 2,
        ]);
        RencanaPertemuan::create([
            'kurikulum_id' => $kurikulum->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'tujuan_pembelajaran_id' => $tp->id,
            'pertemuan_ke' => 1,
            'tanggal_rencana' => '2026-07-13',
            'jp' => 2,
            'status' => RencanaPertemuan::STATUS_DIRENCANAKAN,
        ]);

        return [$guru, $kurikulum, $kelasMapel];
    }

    private function user(string $name): User
    {
        $role = Role::firstOrCreate(['nama_role' => 'guru']);

        return User::create([
            'username' => $name.'-'.uniqid(),
            'email' => $name.'-'.uniqid().'@test.local',
            'password' => 'password',
            'nama_lengkap' => ucfirst($name),
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
