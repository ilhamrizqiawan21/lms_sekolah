<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Materi;
use App\Models\NilaiAkhir;
use App\Models\Pengaturan;
use App\Models\PengumpulanFile;
use App\Models\PengumpulanTugas;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\SoalBank;
use App\Models\SoalBankOpsi;
use App\Models\TahunAjaran;
use App\Models\Tugas;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\User;
use App\Services\AbsensiService;
use App\Services\CbtScoringService;
use App\Services\RekapService;
use App\Services\SiswaLifecycleService;
use App\Services\TugasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditSecurityAndBackendFixesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.require_student_phone' => false]);
    }

    public function test_rekap_service_and_absensi_service_match_carbon_tanggal_correctly(): void
    {
        [$guru, $siswa, $kelasMapel] = $this->createAcademicContext();

        Absensi::create([
            'siswa_id' => $siswa->id,
            'kelas_mapel_id' => $kelasMapel->id,
            'tanggal' => '2026-10-19',
            'pertemuan_ke' => 1,
            'status' => 'hadir',
        ]);

        $rekap = app(RekapService::class)->buildAbsensiRekap($kelasMapel->kelas_id, '2026-10', '1');

        $this->assertSame(['2026-10-19'], $rekap['tanggalList']->all());
        $this->assertSame('hadir', $rekap['rekap'][0]['absensi']['2026-10-19']);
        $this->assertSame(1, $rekap['rekap'][0]['hadir']);

        $rekapKelas = app(AbsensiService::class)->rekapKelas($kelasMapel->id);
        $this->assertCount(1, $rekapKelas);
        $this->assertSame(1, $rekapKelas[0]['rekap']['hadir']);
        $this->assertSame(100.0, $rekapKelas[0]['rekap']['persen_hadir']);
    }

    public function test_admin_can_update_password_when_temporary_password_is_enforced(): void
    {
        config(['security.force_password_change' => true]);
        $adminRole = Role::firstOrCreate(['nama_role' => 'admin']);
        $admin = User::create([
            'username' => 'admin-force',
            'nama_lengkap' => 'Admin Force',
            'password' => Hash::make(User::LEGACY_DEFAULT_PASSWORD),
            'role_id' => $adminRole->id,
            'is_active' => true,
            'is_password_default' => true,
        ]);

        // System settings route (/admin/pengaturan) must be redirected to account settings
        $this->actingAs($admin)
            ->get(route('admin.pengaturan'))
            ->assertRedirect(route('admin.pengaturan-akun'));

        // Account password update route (/admin/pengaturan-akun) must be allowed through
        $response = $this->actingAs($admin)
            ->put(route('admin.pengaturan-akun.update'), [
                'current_password' => User::LEGACY_DEFAULT_PASSWORD,
                'password' => 'NewStrongPass123!',
                'password_confirmation' => 'NewStrongPass123!',
            ]);

        $response->assertSessionHas('success');
        $this->assertFalse((bool) $admin->fresh()->is_password_default);
    }

    public function test_student_with_temporary_password_is_required_to_change_it(): void
    {
        config(['security.force_password_change' => true]);
        $role = Role::firstOrCreate(['nama_role' => 'siswa']);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'Password Change']);
        $student = User::create([
            'username' => 'student-password-change',
            'nama_lengkap' => 'Student Password Change',
            'password' => Hash::make('TemporaryStrong123!'),
            'role_id' => $role->id,
            'is_active' => true,
            'is_password_default' => true,
        ]);
        Siswa::create([
            'user_id' => $student->id,
            'nis' => 'PWD-001',
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);

        $this->actingAs($student)
            ->get(route('siswa.dashboard'))
            ->assertRedirect(route('siswa.pengaturan'));
    }

    public function test_resubmission_replaces_previous_uploaded_files(): void
    {
        Storage::fake('local');
        [, $siswa, $kelasMapel] = $this->createAcademicContext();
        $tugas = Tugas::create([
            'kelas_mapel_id' => $kelasMapel->id,
            'judul' => 'Tugas Revisi',
            'batas_waktu' => now()->addDay(),
            'kategori_nilai' => 'NH',
        ]);

        $this->actingAs($siswa->user)
            ->post(route('siswa.tugas.kumpul', $tugas), [
                'file_upload' => UploadedFile::fake()->createWithContent('lama.pdf', 'versi lama', 'application/pdf'),
            ])
            ->assertRedirect();

        $submission = PengumpulanTugas::where('tugas_id', $tugas->id)
            ->where('siswa_id', $siswa->id)
            ->firstOrFail();
        $oldPath = $submission->file_upload;
        $submission->update(['status' => PengumpulanTugas::STATUS_PERLU_PERBAIKAN]);

        $this->actingAs($siswa->user)
            ->post(route('siswa.tugas.kumpul', $tugas), [
                'file_upload' => UploadedFile::fake()->createWithContent('baru.pdf', 'versi baru', 'application/pdf'),
            ])
            ->assertRedirect();

        $submission->refresh();
        $this->assertCount(1, PengumpulanFile::where('pengumpulan_id', $submission->id)->get());
        $this->assertNotSame($oldPath, $submission->file_upload);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($submission->file_upload);
    }

    public function test_store_kelas_mapel_and_wali_kelas_reject_non_guru_user(): void
    {
        $adminRole = Role::firstOrCreate(['nama_role' => 'admin']);
        $siswaRole = Role::firstOrCreate(['nama_role' => 'siswa']);
        Role::firstOrCreate(['nama_role' => 'guru']);

        $admin = User::create([
            'username' => 'admin-check',
            'nama_lengkap' => 'Admin Check',
            'password' => Hash::make('StrongPass123!'),
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $nonGuru = User::create([
            'username' => 'siswa-as-guru',
            'nama_lengkap' => 'Siswa Bukan Guru',
            'password' => Hash::make('StrongPass123!'),
            'role_id' => $siswaRole->id,
            'is_active' => true,
        ]);

        $tahun = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $mapel = MataPelajaran::create(['kode' => 'IPA', 'nama_mapel' => 'IPA']);

        $this->actingAs($admin)
            ->post(route('admin.kelas-mapel.store'), [
                'kelas_id' => $kelas->id,
                'mapel_id' => $mapel->id,
                'guru_id' => $nonGuru->id,
                'tahun_ajaran_id' => $tahun->id,
                'semester' => '1',
                'pertemuan_per_minggu' => 2,
            ])
            ->assertSessionHasErrors('guru_id');

        $this->actingAs($admin)
            ->post(route('admin.wali-kelas.store'), [
                'kelas_id' => $kelas->id,
                'guru_id' => $nonGuru->id,
                'tahun_ajaran_id' => $tahun->id,
            ])
            ->assertSessionHasErrors('guru_id');
    }

    public function test_materi_download_sanitizes_slashes_in_judul_and_upload_validates_mime(): void
    {
        Storage::fake('local');
        [$guru, $siswa, $kelasMapel] = $this->createAcademicContext();

        // Disguised HTML content named .pdf should fail mimes validation
        $tmpPath = tempnam(sys_get_temp_dir(), 'disguised_pdf_');
        file_put_contents($tmpPath, '<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');
        $disguisedFile = new UploadedFile($tmpPath, 'exploit.pdf', 'application/pdf', null, true);

        try {
            $this->actingAs($guru)
                ->post(route('guru.materi.store', $kelasMapel), [
                    'judul' => 'Materi Disguised',
                    'file_materi' => $disguisedFile,
                ])
                ->assertSessionHasErrors('file_materi');
        } finally {
            @unlink($tmpPath);
        }

        // Download with slash in judul should not throw 500 InvalidArgumentException
        Storage::disk('local')->put('materi/sample.pdf', '%PDF-1.4 dummy content');
        $materi = Materi::create([
            'kelas_mapel_id' => $kelasMapel->id,
            'judul' => 'Bab 1 / Pengenalan \ Aljabar',
            'file_path' => 'materi/sample.pdf',
        ]);

        $this->actingAs($guru)
            ->get(route('guru.materi.download', [$kelasMapel, $materi]))
            ->assertOk();

        $this->actingAs($siswa->user)
            ->get(route('siswa.materi.download', [$kelasMapel, $materi]))
            ->assertOk();
    }

    public function test_nilai_harian_synchronizes_tugas_and_cbt_ujian_without_clobbering(): void
    {
        [$guru, $siswa, $kelasMapel, $tahun] = $this->createAcademicContext();

        $tugas = Tugas::create([
            'kelas_mapel_id' => $kelasMapel->id,
            'judul' => 'Tugas NH 1',
            'batas_waktu' => now()->addDays(2),
        ]);

        $pengumpulan = PengumpulanTugas::create([
            'tugas_id' => $tugas->id,
            'siswa_id' => $siswa->id,
            'status' => PengumpulanTugas::STATUS_SUDAH,
        ]);

        // Teacher grades task = 80
        app(TugasService::class)->gradeSubmission($kelasMapel, $tugas, $siswa, ['nilai' => 80, 'catatan' => 'Bagus']);

        $nilaiAkhir = NilaiAkhir::where('siswa_id', $siswa->id)
            ->where('kelas_mapel_id', $kelasMapel->id)
            ->firstOrFail();
        $this->assertEquals(80.0, (float) $nilaiAkhir->nilai_harian);

        // Now student completes an NH CBT exam with score 100
        $soalBank = SoalBank::create([
            'guru_id' => $guru->id,
            'mapel_id' => $kelasMapel->mapel_id,
            'kategori_nilai' => 'NH',
            'pertanyaan' => '2 + 2 = ?',
            'kesulitan' => 'mudah',
        ]);
        $opsiBenar = SoalBankOpsi::create([
            'soal_bank_id' => $soalBank->id,
            'urutan' => 1,
            'teks_opsi' => '4',
            'is_benar' => true,
        ]);

        $ujian = Ujian::create([
            'kelas_mapel_id' => $kelasMapel->id,
            'judul' => 'Kuis NH 1',
            'durasi_menit' => 30,
            'kategori_nilai' => 'NH',
            'acak_soal' => false,
            'acak_opsi' => false,
        ]);
        $ujian->ujianSoal()->create([
            'soal_bank_id' => $soalBank->id,
            'poin' => 10,
            'urutan' => 1,
        ]);

        $scoringService = app(CbtScoringService::class);
        $attempt = $scoringService->startAttempt($ujian, $siswa);
        $attempt->jawaban()->update(['soal_bank_opsi_id' => $opsiBenar->id]);
        $scoringService->submit($attempt);

        // Combined NH average of 80 (Tugas) and 100 (CBT) must be 90.0
        $nilaiAkhir->refresh();
        $this->assertEquals(90.0, (float) $nilaiAkhir->nilai_harian);

        // Re-grading task to 60 must combine 60 (Tugas) and 100 (CBT) = 80.0
        app(TugasService::class)->gradeSubmission($kelasMapel, $tugas, $siswa, ['nilai' => 60, 'catatan' => 'Revisi']);
        $nilaiAkhir->refresh();
        $this->assertEquals(80.0, (float) $nilaiAkhir->nilai_harian);
    }

    public function test_store_ujian_rejects_duplicate_soal_bank_id_with_validation_error(): void
    {
        [$guru, , $kelasMapel] = $this->createAcademicContext();

        $soalBank = SoalBank::create([
            'guru_id' => $guru->id,
            'mapel_id' => $kelasMapel->mapel_id,
            'kategori_nilai' => 'NH',
            'pertanyaan' => 'Soal unik?',
            'kesulitan' => 'mudah',
        ]);

        $this->actingAs($guru)
            ->post(route('guru.ujian.store', $kelasMapel), [
                'judul' => 'Ujian Duplikat Soal',
                'durasi_menit' => 30,
                'kategori_nilai' => 'NH',
                'acak_soal' => false,
                'acak_opsi' => false,
                'soal' => [
                    ['soal_bank_id' => $soalBank->id, 'poin' => 10],
                    ['soal_bank_id' => $soalBank->id, 'poin' => 10],
                ],
            ])
            ->assertSessionHasErrors('soal.0.soal_bank_id');
    }

    public function test_siswa_lifecycle_service_detects_cbt_attempt_as_academic_history(): void
    {
        [$guru, $siswa, $kelasMapel] = $this->createAcademicContext();

        $ujian = Ujian::create([
            'kelas_mapel_id' => $kelasMapel->id,
            'judul' => 'Ujian History',
            'durasi_menit' => 30,
            'kategori_nilai' => 'STS',
            'acak_soal' => false,
            'acak_opsi' => false,
        ]);

        UjianAttempt::create([
            'ujian_id' => $ujian->id,
            'siswa_id' => $siswa->id,
            'seed' => 12345,
            'urutan_soal_ids' => [],
            'status' => UjianAttempt::STATUS_SELESAI,
            'skor_total' => 80,
            'skor_maksimal' => 100,
        ]);

        $this->assertTrue(app(SiswaLifecycleService::class)->hasAcademicHistory($siswa));
    }

    private function createAcademicContext(): array
    {
        $guruRole = Role::firstOrCreate(['nama_role' => 'guru']);
        $siswaRole = Role::firstOrCreate(['nama_role' => 'siswa']);

        $guru = User::create([
            'username' => 'guru-audit',
            'nama_lengkap' => 'Guru Audit',
            'password' => Hash::make('StrongPass123!'),
            'role_id' => $guruRole->id,
            'is_active' => true,
            'is_password_default' => false,
            'password_changed_at' => now(),
        ]);

        $siswaUser = User::create([
            'username' => 'siswa-audit',
            'nama_lengkap' => 'Siswa Audit',
            'password' => Hash::make('StrongPass123!'),
            'role_id' => $siswaRole->id,
            'is_active' => true,
            'is_password_default' => false,
            'password_changed_at' => now(),
        ]);

        $tahun = TahunAjaran::create(['tahun' => '2026/2027', 'is_active' => true]);
        Pengaturan::setValue('semester_aktif', '1');

        $kelas = Kelas::create(['tingkat' => 'VII', 'nama_kelas' => 'A']);
        $mapel = MataPelajaran::create(['kode' => 'MTK', 'nama_mapel' => 'Matematika']);

        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahun->id,
            'semester' => '1',
            'pertemuan_per_minggu' => 2,
        ]);

        $siswa = Siswa::create([
            'user_id' => $siswaUser->id,
            'nis' => 'AUDIT001',
            'kelas_id' => $kelas->id,
            'status' => 'aktif',
        ]);

        return [$guru, $siswa, $kelasMapel, $tahun];
    }
}
