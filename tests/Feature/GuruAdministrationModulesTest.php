<?php

namespace Tests\Feature;

use App\Models\CapaianPembelajaran;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\Kurikulum;
use App\Models\KurikulumDokumen;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\SoalBank;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianAttemptJawaban;
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruAdministrationModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_can_save_curriculum_documents_and_module_ajar_is_scoped(): void
    {
        [$guru, $kurikulum, $kelasMapel, $tp] = $this->curriculumFixture();

        $this->actingAs($guru)
            ->post(route('guru.kurikulum.dokumen.store'), [
                'kurikulum_id' => $kurikulum->id,
                'kelas_mapel_id' => $kelasMapel->id,
                'jenis' => KurikulumDokumen::JENIS_KKTP,
                'semester' => '1',
                'judul' => 'KKTP Bahasa Indonesia',
                'status' => 'final',
                'isi' => [
                    'skala' => 'interval',
                    'kkm' => 75,
                    'intervals' => [['range' => '0-40', 'label' => 'Perlu bimbingan']],
                    'rows' => [['tp_id' => $tp->id, 'indikator' => 'Mampu menemukan gagasan utama', 'kkm' => 75]],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('kurikulum_dokumen', [
            'kurikulum_id' => $kurikulum->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'jenis' => KurikulumDokumen::JENIS_KKTP,
            'status' => 'final',
        ]);

        $this->actingAs($guru)
            ->get(route('guru.kurikulum.modul-ajar', ['kurikulum_id' => $kurikulum->id]))
            ->assertInertia(fn ($page) => $page
                ->component('Guru/Kurikulum/Dokumen')
                ->where('mode', KurikulumDokumen::JENIS_MODUL_AJAR)
                ->where('selected.id', $kurikulum->id));

        $guruLain = $this->user('Guru Lain', 'guru');
        $this->actingAs($guruLain)
            ->post(route('guru.kurikulum.dokumen.store'), [
                'kurikulum_id' => $kurikulum->id,
                'jenis' => KurikulumDokumen::JENIS_ALOKASI_WAKTU,
                'judul' => 'Tidak boleh',
                'status' => 'draft',
                'isi' => ['jp_per_minggu' => 2],
            ])
            ->assertNotFound();
    }

    public function test_guru_can_open_item_analysis_for_owned_exam_and_cannot_see_other_teacher_exam(): void
    {
        [$guru, $kelasMapel] = $this->teachingFixture();
        $studentUser = $this->user('Siswa Analisis', 'siswa');
        $student = Siswa::create(['user_id' => $studentUser->id, 'nis' => 'NIS-'.uniqid(), 'kelas_id' => $kelasMapel->kelas_id, 'status' => 'aktif']);
        $exam = Ujian::create(['kelas_mapel_id' => $kelasMapel->id, 'judul' => 'Ujian Analisis', 'durasi_menit' => 30, 'kategori_nilai' => 'NH']);
        $question = SoalBank::create(['guru_id' => $guru->id, 'pertanyaan' => '2 + 2 = ?', 'tipe_soal' => SoalBank::TYPE_PILIHAN_GANDA, 'kesulitan' => 'mudah']);
        $examQuestion = UjianSoal::create(['ujian_id' => $exam->id, 'soal_bank_id' => $question->id, 'poin' => 10, 'urutan' => 1]);
        $attempt = UjianAttempt::create([
            'ujian_id' => $exam->id,
            'siswa_id' => $student->id,
            'status' => UjianAttempt::STATUS_SELESAI,
            'seed' => 1,
            'urutan_soal_ids' => [$examQuestion->id],
            'skor_total' => 10,
            'skor_maksimal' => 10,
        ]);
        UjianAttemptJawaban::create([
            'ujian_attempt_id' => $attempt->id,
            'ujian_soal_id' => $examQuestion->id,
            'urutan_opsi_ids' => [],
            'jawaban_teks' => '4',
            'is_benar' => true,
            'poin_didapat' => 10,
            'dijawab_pada' => now(),
        ]);

        $this->actingAs($guru)
            ->get(route('guru.analisis-butir-soal.index', ['ujian_id' => $exam->id]))
            ->assertInertia(fn ($page) => $page
                ->component('Guru/AnalisisButirSoal/Index')
                ->where('analisis.summary.attempts', 1)
                ->where('analisis.items.0.tingkat_kesukaran', 100));

        $otherGuru = $this->user('Guru Lain Analisis', 'guru');
        $this->actingAs($otherGuru)
            ->get(route('guru.analisis-butir-soal.index', ['ujian_id' => $exam->id]))
            ->assertNotFound();
    }

    private function curriculumFixture(): array
    {
        [$guru, $kelasMapel] = $this->teachingFixture();
        $kurikulum = Kurikulum::create([
            'tahun_ajaran_id' => $kelasMapel->tahun_ajaran_id,
            'mapel_id' => $kelasMapel->mapel_id,
            'guru_id' => $guru->id,
            'semester' => '1',
            'jenis_kurikulum' => 'merdeka',
            'nama' => 'ATP Bahasa Indonesia',
        ]);
        $kurikulum->kelasMapel()->attach($kelasMapel);
        $cp = CapaianPembelajaran::create(['kurikulum_id' => $kurikulum->id, 'kode' => 'CP-1', 'deskripsi' => 'Memahami teks.', 'urutan' => 1]);
        $tp = $kurikulum->tujuanPembelajaran()->create([
            'capaian_pembelajaran_id' => $cp->id,
            'kode' => 'TP-1',
            'bab' => 'Bab 1',
            'tujuan' => 'Menemukan gagasan utama.',
            'alokasi_jp' => 2,
            'estimasi_pertemuan' => 1,
        ]);

        return [$guru, $kurikulum, $kelasMapel, $tp];
    }

    private function teachingFixture(): array
    {
        $guru = $this->user('Guru Utama', 'guru');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'BIN', 'nama_mapel' => 'Bahasa Indonesia', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);

        return [$guru, $kelasMapel];
    }

    private function user(string $name, string $roleName): User
    {
        $role = Role::firstOrCreate(['nama_role' => $roleName]);

        return User::create([
            'username' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid(),
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@test.local',
            'password' => Hash::make('password'),
            'nama_lengkap' => $name,
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }
}
