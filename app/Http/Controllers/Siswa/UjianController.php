<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Siswa\SimpanJawabanUjianRequest;
use App\Models\KelasMapel;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianAttemptJawaban;
use App\Models\UjianSoal;
use App\Services\CbtScoringService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class UjianController extends Controller
{
    public function __construct(
        protected CbtScoringService $scoringService
    ) {}

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $siswa = $user->siswa;

        if (! $siswa) {
            abort(403, 'Akses khusus profil siswa aktif.');
        }

        $activeKelasMapelIds = KelasMapel::query()
            ->where('kelas_id', $siswa->kelas_id)
            ->aktif()
            ->pluck('id');

        $ujianList = Ujian::query()
            ->with(['kelasMapel.mataPelajaran', 'kelasMapel.guru', 'ujianSoal'])
            ->whereIn('kelas_mapel_id', $activeKelasMapelIds)
            ->latest('id')
            ->get()
            ->map(function ($u) use ($siswa) {
                $attempt = UjianAttempt::where('ujian_id', $u->id)
                    ->where('siswa_id', $siswa->id)
                    ->first();

                $isBuka = $u->isBuka();

                $statusLabel = 'Belum Dikerjakan';
                $canStart = $isBuka && ! $attempt;
                $canContinue = false;
                $canViewHasil = false;

                if ($attempt) {
                    if ($attempt->status === UjianAttempt::STATUS_SEDANG_MENGERJAKAN) {
                        if (Carbon::now()->gt($attempt->batas_waktu)) {
                            $this->scoringService->autoSubmitKarenaWaktuHabis($attempt);
                            $attempt->refresh();
                            $statusLabel = 'Waktu Habis';
                            $canViewHasil = true;
                        } else {
                            $statusLabel = 'Sedang Dikerjakan';
                            $canContinue = true;
                        }
                    } elseif ($attempt->status === UjianAttempt::STATUS_SELESAI) {
                        $skor100 = $attempt->skor_maksimal > 0
                            ? round(($attempt->skor_total / $attempt->skor_maksimal) * 100, 2)
                            : 0;
                        $statusLabel = "Selesai (Nilai: {$skor100})";
                        $canViewHasil = true;
                    } elseif ($attempt->status === UjianAttempt::STATUS_WAKTU_HABIS) {
                        $skor100 = $attempt->skor_maksimal > 0
                            ? round(($attempt->skor_total / $attempt->skor_maksimal) * 100, 2)
                            : 0;
                        $statusLabel = "Waktu Habis (Nilai: {$skor100})";
                        $canViewHasil = true;
                    }
                }

                return [
                    'id' => $u->id,
                    'judul' => $u->judul,
                    'deskripsi' => $u->deskripsi,
                    'mata_pelajaran' => $u->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
                    'guru' => $u->kelasMapel?->guru?->nama_lengkap ?? '-',
                    'durasi_menit' => $u->durasi_menit,
                    'kategori_nilai' => $u->kategori_nilai,
                    'waktu_mulai' => $u->waktu_mulai?->format('d/m/Y H:i'),
                    'waktu_selesai' => $u->waktu_selesai?->format('d/m/Y H:i'),
                    'total_soal' => $u->ujianSoal->count(),
                    'is_buka' => $isBuka,
                    'status_label' => $statusLabel,
                    'can_start' => $canStart,
                    'can_continue' => $canContinue,
                    'can_view_hasil' => $canViewHasil,
                    'attempt_id' => $attempt?->id,
                    'mulai_url' => route('siswa.ujian.mulai', $u->id),
                    'kerjakan_url' => $attempt ? route('siswa.ujian.kerjakan', $attempt->id) : null,
                    'hasil_url' => $attempt ? route('siswa.ujian.hasil', $attempt->id) : null,
                ];
            });

        return Inertia::render('Siswa/Ujian/Index', [
            'ujianList' => $ujianList,
        ]);
    }

    public function mulai(Ujian $ujian): RedirectResponse
    {
        $user = Auth::user();
        $siswa = $user->siswa;

        if (! $siswa) {
            abort(403, 'Akses khusus siswa.');
        }

        // Pastikan kelas_mapel milik kelas siswa dan aktif
        $kelasMapel = $ujian->kelasMapel;
        if (! $kelasMapel || (int) $kelasMapel->kelas_id !== (int) $siswa->kelas_id || ! $kelasMapel->isAktif()) {
            abort(403, 'Anda tidak terdaftar di kelas mata pelajaran untuk ujian ini.');
        }

        if (! $ujian->isBuka()) {
            return redirect()->back()->withErrors([
                'error' => 'Ujian belum dibuka atau sudah melewati batas waktu akses.',
            ]);
        }

        // Idempotency: jika sudah ada attempt, redirect ke kerjakan atau hasil
        $existing = UjianAttempt::where('ujian_id', $ujian->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($existing) {
            if (in_array($existing->status, UjianAttempt::STATUS_TERKUNCI, true)) {
                return redirect()->route('siswa.ujian.hasil', $existing->id);
            }

            return redirect()->route('siswa.ujian.kerjakan', $existing->id);
        }

        $attempt = DB::transaction(function () use ($ujian, $siswa) {
            $now = Carbon::now();
            $durasi = (int) $ujian->durasi_menit;
            $batasWaktu = (clone $now)->addMinutes($durasi);

            if ($ujian->waktu_selesai && $batasWaktu->gt($ujian->waktu_selesai)) {
                $batasWaktu = clone $ujian->waktu_selesai;
            }

            $attempt = UjianAttempt::create([
                'ujian_id' => $ujian->id,
                'siswa_id' => $siswa->id,
                'status' => UjianAttempt::STATUS_SEDANG_MENGERJAKAN,
                'seed' => 0,
                'urutan_soal_ids' => [],
                'waktu_mulai' => $now,
                'batas_waktu' => $batasWaktu,
                'skor_total' => 0,
                'skor_maksimal' => 0,
                'tab_switch_log' => [],
                'tab_switch_count' => 0,
            ]);

            $seed = (int) $attempt->id;
            $attempt->seed = $seed;

            $ujianSoalList = UjianSoal::with('soalBank.opsi')
                ->where('ujian_id', $ujian->id)
                ->orderBy('urutan')
                ->get();

            $soalIds = $ujianSoalList->pluck('id')->all();

            if ($ujian->acak_soal) {
                // Deterministic shuffle with seed
                mt_srand($seed);
                for ($i = count($soalIds) - 1; $i > 0; $i--) {
                    $j = mt_rand(0, $i);
                    $tmp = $soalIds[$i];
                    $soalIds[$i] = $soalIds[$j];
                    $soalIds[$j] = $tmp;
                }
            }

            $attempt->urutan_soal_ids = $soalIds;
            $attempt->save();

            // Create initial UjianAttemptJawaban rows with deterministic option ordering
            foreach ($ujianSoalList as $us) {
                $opsiList = $us->soalBank?->opsi ?? collect();
                $opsiIds = $opsiList->sortBy('urutan')->pluck('id')->all();

                if ($ujian->acak_opsi && count($opsiIds) > 1) {
                    mt_srand($seed + (int) $us->id);
                    for ($i = count($opsiIds) - 1; $i > 0; $i--) {
                        $j = mt_rand(0, $i);
                        $tmp = $opsiIds[$i];
                        $opsiIds[$i] = $opsiIds[$j];
                        $opsiIds[$j] = $tmp;
                    }
                }

                UjianAttemptJawaban::create([
                    'ujian_attempt_id' => $attempt->id,
                    'ujian_soal_id' => $us->id,
                    'soal_bank_opsi_id' => null,
                    'urutan_opsi_ids' => $opsiIds,
                    'is_benar' => null,
                    'poin_didapat' => null,
                    'dijawab_pada' => null,
                ]);
            }

            return $attempt;
        });

        return redirect()->route('siswa.ujian.kerjakan', $attempt->id);
    }

    public function kerjakan(UjianAttempt $attempt): Response|RedirectResponse
    {
        $user = Auth::user();
        $siswa = $user->siswa;
        $this->ensureAttemptBelongsToSiswa($attempt, $siswa);

        // Lazy auto-submit jika batas waktu sudah terlewati
        if ($attempt->status === UjianAttempt::STATUS_SEDANG_MENGERJAKAN && Carbon::now()->gt($attempt->batas_waktu)) {
            $this->scoringService->autoSubmitKarenaWaktuHabis($attempt);

            return redirect()->route('siswa.ujian.hasil', $attempt->id)
                ->with('info', 'Waktu pengerjaan telah habis. Jawaban Anda telah dikumpulkan secara otomatis.');
        }

        if (in_array($attempt->status, UjianAttempt::STATUS_TERKUNCI, true)) {
            return redirect()->route('siswa.ujian.hasil', $attempt->id);
        }

        $attempt->loadMissing([
            'ujian.kelasMapel.kelas',
            'ujian.kelasMapel.mataPelajaran',
            'jawaban.ujianSoal.soalBank.opsi',
        ]);

        $ujian = $attempt->ujian;
        $urutanSoalIds = $attempt->urutan_soal_ids ?? [];
        $jawabanMap = $attempt->jawaban->keyBy('ujian_soal_id');

        $soalPayload = [];

        foreach ($urutanSoalIds as $idx => $ujianSoalId) {
            $jawaban = $jawabanMap->get($ujianSoalId);
            if (! $jawaban || ! $jawaban->ujianSoal || ! $jawaban->ujianSoal->soalBank) {
                continue;
            }

            $soalBank = $jawaban->ujianSoal->soalBank;
            $urutanOpsiIds = $jawaban->urutan_opsi_ids ?? $soalBank->opsi->pluck('id')->all();
            $opsiMap = $soalBank->opsi->keyBy('id');

            $opsiOrdered = [];
            foreach ($urutanOpsiIds as $oIdx => $opsiId) {
                $opsi = $opsiMap->get($opsiId);
                if ($opsi) {
                    $opsiOrdered[] = [
                        'id' => $opsi->id,
                        'urutan' => $oIdx + 1,
                        'teks_opsi' => $opsi->teks_opsi,
                    ];
                }
            }

            $soalPayload[] = [
                'attempt_jawaban_id' => $jawaban->id,
                'urutan' => $idx + 1,
                'pertanyaan' => $soalBank->pertanyaan,
                'ragu_ragu' => (bool) ($jawaban->ragu_ragu ?? false),
                'jawaban_opsi_id' => $jawaban->soal_bank_opsi_id,
                'opsi' => $opsiOrdered,
            ];
        }

        $sisaDetik = max(0, Carbon::now()->diffInSeconds($attempt->batas_waktu, false));

        return Inertia::render('Siswa/Ujian/Kerjakan', [
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'sisa_detik' => $sisaDetik,
                'waktu_mulai' => $attempt->waktu_mulai?->format('Y-m-d H:i:s'),
                'waktu_selesai' => $attempt->waktu_submit?->format('Y-m-d H:i:s'),
                'tab_switches_count' => (int) $attempt->tab_switch_count,
            ],
            'ujian' => [
                'id' => $ujian->id,
                'judul' => $ujian->judul,
                'deskripsi' => $ujian->deskripsi,
                'durasi_menit' => $ujian->durasi_menit,
                'kelas' => $ujian->kelasMapel?->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $ujian->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            ],
            'soalList' => $soalPayload,
            'submitUrl' => route('siswa.ujian.submit', $attempt->id),
            'simpanJawabanUrl' => route('siswa.ujian.jawab', $attempt->id),
            'logTabSwitchUrl' => route('siswa.ujian.tab-switch', $attempt->id),
        ]);
    }

    public function jawab(SimpanJawabanUjianRequest $request, UjianAttempt $attempt): JsonResponse
    {
        $user = Auth::user();
        $siswa = $user->siswa;
        $this->ensureAttemptBelongsToSiswa($attempt, $siswa);

        // Periksa apakah waktu habis atau attempt sudah terkunci
        if (in_array($attempt->status, UjianAttempt::STATUS_TERKUNCI, true) || Carbon::now()->gt($attempt->batas_waktu)) {
            if ($attempt->status === UjianAttempt::STATUS_SEDANG_MENGERJAKAN) {
                $this->scoringService->autoSubmitKarenaWaktuHabis($attempt);
            }

            return response()->json([
                'message' => 'Waktu pengerjaan ujian telah habis atau ujian telah selesai.',
            ], 422);
        }

        $validated = $request->validated();
        $jawaban = UjianAttemptJawaban::where('id', $validated['attempt_jawaban_id'])
            ->where('ujian_attempt_id', $attempt->id)
            ->firstOrFail();

        $jawaban->update([
            'soal_bank_opsi_id' => $validated['jawaban_opsi_id'] ?? null,
            'dijawab_pada' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'attempt_jawaban_id' => $jawaban->id,
            'jawaban_opsi_id' => $jawaban->soal_bank_opsi_id,
        ]);
    }

    public function submit(UjianAttempt $attempt): RedirectResponse
    {
        $user = Auth::user();
        $siswa = $user->siswa;
        $this->ensureAttemptBelongsToSiswa($attempt, $siswa);

        if ($attempt->status === UjianAttempt::STATUS_SEDANG_MENGERJAKAN) {
            if (Carbon::now()->gt($attempt->batas_waktu)) {
                $this->scoringService->autoSubmitKarenaWaktuHabis($attempt);
            } else {
                $this->scoringService->submit($attempt);
            }
        }

        return redirect()->route('siswa.ujian.hasil', $attempt->id)->with('success', 'Ujian berhasil dikumpulkan.');
    }

    public function logTabSwitch(UjianAttempt $attempt): HttpResponse
    {
        $user = Auth::user();
        $siswa = $user->siswa;
        $this->ensureAttemptBelongsToSiswa($attempt, $siswa);

        if ($attempt->status === UjianAttempt::STATUS_SEDANG_MENGERJAKAN) {
            $log = $attempt->tab_switch_log ?? [];
            $log[] = Carbon::now()->toIso8601String();

            $attempt->update([
                'tab_switch_log' => $log,
                'tab_switch_count' => count($log),
            ]);
        }

        return response()->noContent();
    }

    public function hasil(UjianAttempt $attempt): Response
    {
        $user = Auth::user();
        $siswa = $user->siswa;
        $this->ensureAttemptBelongsToSiswa($attempt, $siswa);

        // Lazy auto-submit jika status masih sedang_mengerjakan
        if ($attempt->status === UjianAttempt::STATUS_SEDANG_MENGERJAKAN) {
            if (Carbon::now()->gt($attempt->batas_waktu)) {
                $this->scoringService->autoSubmitKarenaWaktuHabis($attempt);
                $attempt->refresh();
            } else {
                return redirect()->route('siswa.ujian.kerjakan', $attempt->id);
            }
        }

        $attempt->loadMissing([
            'ujian.kelasMapel.mataPelajaran',
            'ujian.kelasMapel.kelas',
            'jawaban',
        ]);

        $ujian = $attempt->ujian;
        $totalSoal = $attempt->jawaban->count();
        $totalBenar = $attempt->jawaban->where('is_benar', true)->count();
        $totalSalah = $attempt->jawaban->where('is_benar', false)->whereNotNull('soal_bank_opsi_id')->count();
        $totalKosong = $attempt->jawaban->whereNull('soal_bank_opsi_id')->count();

        $skor100 = $attempt->skor_maksimal > 0
            ? round(($attempt->skor_total / $attempt->skor_maksimal) * 100, 2)
            : 0;

        return Inertia::render('Siswa/Ujian/Hasil', [
            'attempt' => [
                'id' => $attempt->id,
                'status' => $attempt->status,
                'waktu_mulai' => $attempt->waktu_mulai?->format('d/m/Y H:i:s'),
                'waktu_selesai' => $attempt->waktu_submit?->format('d/m/Y H:i:s'),
                'skor_total' => (float) $attempt->skor_total,
                'skor_maksimal' => (float) $attempt->skor_maksimal,
                'nilai' => $skor100,
                'total_soal' => $totalSoal,
                'total_benar' => $totalBenar,
                'total_salah' => $totalSalah,
                'total_kosong' => $totalKosong,
                'tab_switches_count' => (int) $attempt->tab_switch_count,
            ],
            'ujian' => [
                'id' => $ujian->id,
                'judul' => $ujian->judul,
                'deskripsi' => $ujian->deskripsi,
                'kategori_nilai' => $ujian->kategori_nilai,
                'kelas' => $ujian->kelasMapel?->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $ujian->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            ],
        ]);
    }

    private function ensureAttemptBelongsToSiswa(UjianAttempt $attempt, ?Siswa $siswa): void
    {
        abort_unless($siswa && (int) $attempt->siswa_id === (int) $siswa->id, 403, 'Attempt ini bukan milik Anda.');
    }
}

