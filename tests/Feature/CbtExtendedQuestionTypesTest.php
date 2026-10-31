<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\MataPelajaran;
use App\Models\Role;
use App\Models\Siswa;
use App\Models\SoalBank;
use App\Models\SoalBankOpsi;
use App\Models\TahunAjaran;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianAttemptJawaban;
use App\Models\UjianSoal;
use App\Models\User;
use App\Services\Reports\Exports\GuruReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CbtExtendedQuestionTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_matching_is_scored_and_essay_is_saved_then_graded_by_teacher(): void
    {
        [$guru, $siswaUser, $ujian, $matching, $essay] = $this->makeFixture();

        $response = $this->actingAs($siswaUser)->post(route('siswa.ujian.mulai', $ujian));
        $response->assertRedirect();

        $attempt = UjianAttempt::query()->firstOrFail();
        $matchingAnswer = UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)
            ->where('ujian_soal_id', $matching->id)
            ->firstOrFail();
        $essayAnswer = UjianAttemptJawaban::where('ujian_attempt_id', $attempt->id)
            ->where('ujian_soal_id', $essay->id)
            ->firstOrFail();

        $leftOptions = SoalBankOpsi::where('soal_bank_id', $matching->soal_bank_id)->orderBy('id')->get();
        $this->actingAs($siswaUser)->postJson(route('siswa.ujian.jawab', $attempt), [
            'attempt_jawaban_id' => $matchingAnswer->id,
            'jawaban_pasangan' => [
                (string) $leftOptions[0]->id => $leftOptions[1]->id,
                (string) $leftOptions[1]->id => $leftOptions[1]->id,
            ],
        ])->assertStatus(422);

        $this->actingAs($siswaUser)->postJson(route('siswa.ujian.jawab', $attempt), [
            'attempt_jawaban_id' => $matchingAnswer->id,
            'jawaban_pasangan' => [
                (string) $leftOptions[0]->id => $leftOptions[0]->id,
                (string) $leftOptions[1]->id => $leftOptions[1]->id,
            ],
        ])->assertOk();

        $this->actingAs($siswaUser)->postJson(route('siswa.ujian.jawab', $attempt), [
            'attempt_jawaban_id' => $essayAnswer->id,
            'jawaban_teks' => 'Jawaban essay siswa yang perlu diperiksa guru.',
        ])->assertOk();

        $this->actingAs($siswaUser)->post(route('siswa.ujian.submit', $attempt))
            ->assertRedirect(route('siswa.ujian.hasil', $attempt));

        $attempt->refresh();
        $this->assertSame(10.0, (float) $attempt->skor_total);
        $this->assertSame(30.0, (float) $attempt->skor_maksimal);
        $this->assertTrue((bool) $matchingAnswer->fresh()->is_benar);
        $this->assertNull($essayAnswer->fresh()->poin_didapat);
        $this->assertSame('Jawaban essay siswa yang perlu diperiksa guru.', $essayAnswer->fresh()->jawaban_teks);

        $report = app(GuruReportService::class)->ujianHasil($ujian->kelasMapel, $ujian);
        $this->assertContains('Essay Belum Dinilai', $report['headers']);
        $this->assertSame(1, $report['rows'][0][5]);

        $this->actingAs($guru)->put(route('guru.ujian.attempt.jawaban.grade', [
            $ujian->kelas_mapel_id,
            $ujian->id,
            $attempt->id,
            $essayAnswer->id,
        ]), ['poin_didapat' => 99])->assertSessionHasErrors('poin_didapat');

        $this->actingAs($guru)->put(route('guru.ujian.attempt.jawaban.grade', [
            $ujian->kelas_mapel_id,
            $ujian->id,
            $attempt->id,
            $essayAnswer->id,
        ]), ['poin_didapat' => 15, 'catatan' => 'Argumen sudah tepat, tetapi perlu contoh tambahan.'])->assertRedirect();

        $attempt->refresh();
        $this->assertSame(25.0, (float) $attempt->skor_total);
        $this->assertSame(30.0, (float) $attempt->skor_maksimal);
        $this->assertSame(15.0, (float) $essayAnswer->fresh()->poin_didapat);
        $this->assertFalse((bool) $essayAnswer->fresh()->is_benar);
        $this->assertDatabaseHas('ujian_attempt_jawaban_penilaian', [
            'ujian_attempt_jawaban_id' => $essayAnswer->id,
            'dinilai_oleh' => $guru->id,
            'poin_diberikan' => 15,
            'catatan' => 'Argumen sudah tepat, tetapi perlu contoh tambahan.',
        ]);
        $this->assertDatabaseHas('notifikasi', [
            'user_id' => $siswaUser->id,
            'tipe' => 'nilai_ujian_diperbarui',
        ]);

        $this->actingAs($siswaUser)->get(route('siswa.ujian.hasil', $attempt))
            ->assertInertia(fn ($page) => $page
                ->where('soalHasil.1.pembahasan', 'Gunakan konsep dan contoh yang relevan.')
                ->where('soalHasil.1.jawaban_teks', 'Jawaban essay siswa yang perlu diperiksa guru.'));

        $detailReport = app(GuruReportService::class)->ujianJawabanDetail($ujian->kelasMapel, $ujian);
        $this->assertContains('Jawaban Siswa', $detailReport['headers']);
        $this->assertCount(2, $detailReport['rows']);
    }

    public function test_teacher_can_select_matching_and_essay_questions_in_an_exam(): void
    {
        [$guru, , , $matching, $essay] = $this->makeFixture();

        $response = $this->actingAs($guru)->post(route('guru.ujian.store', $matching->ujian->kelas_mapel_id), [
            'judul' => 'Ujian Tipe Lengkap',
            'durasi_menit' => 45,
            'kategori_nilai' => 'NH',
            'acak_soal' => false,
            'acak_opsi' => true,
            'soal' => [
                ['soal_bank_id' => $matching->soal_bank_id, 'poin' => 10],
                ['soal_bank_id' => $essay->soal_bank_id, 'poin' => 20],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('ujian_soal', 4);
    }

    /** @return array{0: User, 1: User, 2: Ujian, 3: UjianSoal, 4: UjianSoal} */
    private function makeFixture(): array
    {
        $guru = $this->makeUser('Guru Extended', 'guru');
        $siswaUser = $this->makeUser('Siswa Extended', 'siswa');
        $tahunAjaran = TahunAjaran::create(['tahun' => '2025/2026', 'is_active' => true]);
        $mapel = MataPelajaran::create(['kode' => 'EXT', 'nama_mapel' => 'Bahasa', 'urutan' => 1]);
        $kelas = Kelas::create(['tingkat' => '10', 'nama_kelas' => '10-EXT']);
        $kelasMapel = KelasMapel::create([
            'kelas_id' => $kelas->id,
            'mapel_id' => $mapel->id,
            'guru_id' => $guru->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'semester' => '1',
            'status' => 'aktif',
        ]);
        Siswa::create([
            'user_id' => $siswaUser->id,
            'kelas_id' => $kelas->id,
            'nama_lengkap' => $siswaUser->nama_lengkap,
            'nis' => 'NIS'.uniqid(),
            'status' => 'aktif',
        ]);

        $matchingBank = SoalBank::create([
            'guru_id' => $guru->id,
            'pertanyaan' => 'Jodohkan istilah dengan jawabannya.',
            'tipe_soal' => SoalBank::TYPE_MENJODOHKAN,
            'kesulitan' => 'sedang',
        ]);
        SoalBankOpsi::create([
            'soal_bank_id' => $matchingBank->id,
            'teks_opsi' => 'Ibukota Indonesia',
            'pasangan_teks' => 'Jakarta',
            'urutan' => 1,
        ]);
        SoalBankOpsi::create([
            'soal_bank_id' => $matchingBank->id,
            'teks_opsi' => 'Planet Merah',
            'pasangan_teks' => 'Mars',
            'urutan' => 2,
        ]);

        $essayBank = SoalBank::create([
            'guru_id' => $guru->id,
            'pertanyaan' => 'Jelaskan makna belajar menurut Anda.',
            'pembahasan' => 'Gunakan konsep dan contoh yang relevan.',
            'tipe_soal' => SoalBank::TYPE_URAIAN,
            'kesulitan' => 'sedang',
        ]);

        $ujian = Ujian::create([
            'kelas_mapel_id' => $kelasMapel->id,
            'judul' => 'Ujian Extended',
            'durasi_menit' => 30,
            'kategori_nilai' => 'NH',
            'acak_soal' => false,
            'acak_opsi' => false,
        ]);
        $matching = UjianSoal::create(['ujian_id' => $ujian->id, 'soal_bank_id' => $matchingBank->id, 'poin' => 10, 'urutan' => 1]);
        $essay = UjianSoal::create(['ujian_id' => $ujian->id, 'soal_bank_id' => $essayBank->id, 'poin' => 20, 'urutan' => 2]);

        return [$guru, $siswaUser, $ujian, $matching, $essay];
    }

    public function test_scheduler_command_auto_submits_expired_attempts(): void
    {
        [, $siswaUser, $ujian] = $this->makeFixture();

        $this->actingAs($siswaUser)->post(route('siswa.ujian.mulai', $ujian));
        $attempt = UjianAttempt::query()->firstOrFail();
        $attempt->update(['batas_waktu' => now()->subMinute()]);

        $this->artisan('ujian:auto-submit-expired')->assertExitCode(0);

        $this->assertSame(UjianAttempt::STATUS_WAKTU_HABIS, $attempt->fresh()->status);
    }

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
}
