<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\StoreUjianRequest;
use App\Http\Requests\Guru\UpdateUjianRequest;
use App\Models\KelasMapel;
use App\Models\Siswa;
use App\Models\SoalBank;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianSoal;
use App\Services\CbtScoringService;
use App\Services\UjianService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class UjianController extends Controller
{
    public function __construct(
        protected UjianService $ujianService,
        protected CbtScoringService $scoringService,
    ) {}

    public function index(): Response
    {
        $kelasMapel = KelasMapel::with(['kelas', 'mataPelajaran', 'tahunAjaran'])
            ->where('guru_id', Auth::id())
            ->aktif()
            ->get();

        $ujianList = Ujian::with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran'])
            ->whereIn('kelas_mapel_id', $kelasMapel->pluck('id'))
            ->withCount('ujianSoal as total_soal')
            ->withCount('attempts as total_attempts')
            ->withCount(['attempts as selesai_attempts' => function ($q) {
                $q->whereIn('status', UjianAttempt::STATUS_TERKUNCI);
            }])
            ->orderByDesc('id')
            ->get();

        $siswaAktifByKelas = Siswa::whereIn('kelas_id', $kelasMapel->pluck('kelas_id')->unique())
            ->where('status', 'aktif')
            ->selectRaw('kelas_id, count(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');

        return Inertia::render('Guru/Ujian/Index', [
            'kelasMapel' => $kelasMapel->map(fn (KelasMapel $item) => [
                'id' => $item->id,
                'kelas' => $item->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $item->mataPelajaran?->nama_mapel ?? '-',
                'semester' => $item->semester,
                'label' => ($item->kelas?->displayName() ?? '-').' - '.($item->mataPelajaran?->nama_mapel ?? '-').' (Sem. '.$item->semester.')',
                'href' => route('guru.ujian.list', $item),
            ])->values(),
            'ujian' => $ujianList->map(function (Ujian $item) use ($siswaAktifByKelas) {
                $totalSiswa = (int) ($siswaAktifByKelas[$item->kelasMapel?->kelas_id] ?? 0);
                $selesai = (int) $item->selesai_attempts;

                return [
                    'id' => $item->id,
                    'kelas_mapel_id' => $item->kelas_mapel_id,
                    'judul' => $item->judul,
                    'deskripsi' => (string) $item->deskripsi,
                    'durasi_menit' => $item->durasi_menit,
                    'kategori_nilai' => $item->kategori_nilai,
                    'waktu_mulai' => $item->waktu_mulai?->format('d/m/Y H:i'),
                    'waktu_selesai' => $item->waktu_selesai?->format('d/m/Y H:i'),
                    'is_buka' => $item->isBuka(),
                    'total_soal' => (int) $item->total_soal,
                    'total_attempts' => (int) $item->total_attempts,
                    'selesai_attempts' => $selesai,
                    'total_siswa' => $totalSiswa,
                    'progress_percent' => $totalSiswa > 0 ? round(($selesai / $totalSiswa) * 100) : 0,
                    'kelas' => $item->kelasMapel?->kelas?->displayName() ?? '-',
                    'mata_pelajaran' => $item->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
                    'hasil_url' => $item->kelasMapel ? route('guru.ujian.hasil', [$item->kelasMapel, $item]) : null,
                    'edit_url' => $item->kelasMapel ? route('guru.ujian.edit', [$item->kelasMapel, $item]) : null,
                    'delete_url' => route('guru.ujian.destroy', $item),
                ];
            })->values(),
            'metrics' => [
                'total_ujian' => $ujianList->count(),
                'ujian_aktif' => $ujianList->filter(fn (Ujian $u) => $u->isBuka())->count(),
                'total_selesai' => $ujianList->sum('selesai_attempts'),
            ],
        ]);
    }

    public function list(KelasMapel $kelasMapel): Response
    {
        $this->authorize('mengajar', $kelasMapel);

        $ujianList = Ujian::where('kelas_mapel_id', $kelasMapel->id)
            ->withCount('ujianSoal as total_soal')
            ->withCount('attempts as total_attempts')
            ->withCount(['attempts as selesai_attempts' => function ($q) {
                $q->whereIn('status', UjianAttempt::STATUS_TERKUNCI);
            }])
            ->orderByDesc('id')
            ->get();

        $totalSiswa = Siswa::where('kelas_id', $kelasMapel->kelas_id)
            ->where('status', 'aktif')
            ->count();

        return Inertia::render('Guru/Ujian/List', [
            'kelasMapel' => [
                'id' => $kelasMapel->id,
                'kelas' => $kelasMapel->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $kelasMapel->mataPelajaran?->nama_mapel ?? '-',
                'semester' => $kelasMapel->semester,
            ],
            'ujian' => $ujianList->map(function (Ujian $item) use ($kelasMapel, $totalSiswa) {
                $selesai = (int) $item->selesai_attempts;

                return [
                    'id' => $item->id,
                    'kelas_mapel_id' => $item->kelas_mapel_id,
                    'judul' => $item->judul,
                    'deskripsi' => (string) $item->deskripsi,
                    'durasi_menit' => $item->durasi_menit,
                    'kategori_nilai' => $item->kategori_nilai,
                    'waktu_mulai' => $item->waktu_mulai?->format('d/m/Y H:i'),
                    'waktu_selesai' => $item->waktu_selesai?->format('d/m/Y H:i'),
                    'is_buka' => $item->isBuka(),
                    'total_soal' => (int) $item->total_soal,
                    'total_attempts' => (int) $item->total_attempts,
                    'selesai_attempts' => $selesai,
                    'total_siswa' => $totalSiswa,
                    'progress_percent' => $totalSiswa > 0 ? round(($selesai / $totalSiswa) * 100) : 0,
                    'hasil_url' => route('guru.ujian.hasil', [$kelasMapel, $item]),
                    'edit_url' => route('guru.ujian.edit', [$kelasMapel, $item]),
                    'delete_url' => route('guru.ujian.destroy', $item),
                ];
            })->values(),
            'totalSiswa' => $totalSiswa,
            'createUrl' => route('guru.ujian.create', $kelasMapel),
        ]);
    }

    public function create(KelasMapel $kelasMapel): Response
    {
        $this->authorize('mengajar', $kelasMapel);

        return $this->renderBuilder($kelasMapel, null);
    }

    public function edit(KelasMapel $kelasMapel, Ujian $ujian): Response
    {
        $this->authorize('mengajar', $kelasMapel);
        $this->ensureUjianBelongsToKelasMapel($ujian, $kelasMapel);

        return $this->renderBuilder($kelasMapel, $ujian);
    }

    public function store(StoreUjianRequest $request, KelasMapel $kelasMapel): RedirectResponse
    {
        $this->authorize('mengajar', $kelasMapel);

        $this->ujianService->createUjian($kelasMapel, $request->validated());

        return redirect()->route('guru.ujian.list', $kelasMapel)
            ->with('success', 'Ujian CBT berhasil dibuat.');
    }

    public function update(UpdateUjianRequest $request, KelasMapel $kelasMapel, Ujian $ujian): RedirectResponse
    {
        $this->authorize('mengajar', $kelasMapel);
        $this->ensureUjianBelongsToKelasMapel($ujian, $kelasMapel);

        $this->ujianService->updateUjian($ujian, $request->validated());

        return redirect()->route('guru.ujian.list', $kelasMapel)
            ->with('success', 'Ujian CBT berhasil diperbarui.');
    }

    public function destroy(Ujian $ujian): RedirectResponse
    {
        $this->authorize('mengajar-ujian', $ujian);

        if ($ujian->attempts()->exists()) {
            return back()->with('error', 'Ujian tidak dapat dihapus karena sudah ada siswa yang mengerjakan.');
        }

        $ujian->delete();

        return back()->with('success', 'Ujian CBT berhasil dihapus.');
    }

    public function hasil(KelasMapel $kelasMapel, Ujian $ujian): Response
    {
        $this->authorize('mengajar', $kelasMapel);
        $this->ensureUjianBelongsToKelasMapel($ujian, $kelasMapel);

        $ujian->loadCount('ujianSoal as total_soal');

        $siswaAktif = Siswa::with('user')
            ->where('kelas_id', $kelasMapel->kelas_id)
            ->where('status', 'aktif')
            ->orderBy('nis')
            ->get();

        $attempts = UjianAttempt::with('siswa.user')
            ->where('ujian_id', $ujian->id)
            ->get()
            ->keyBy('siswa_id');

        $selesaiItems = [];
        $belumItems = [];
        $skorList = [];

        foreach ($siswaAktif as $siswa) {
            $attempt = $attempts->get($siswa->id);

            if (! $attempt || $attempt->status === UjianAttempt::STATUS_BELUM_MULAI) {
                $belumItems[] = [
                    'id' => $siswa->id,
                    'nama_lengkap' => $siswa->user?->nama_lengkap ?? $siswa->nis,
                    'nis' => $siswa->nis,
                    'nisn' => $siswa->nisn,
                ];

                continue;
            }

            $statusMap = [
                UjianAttempt::STATUS_SEDANG_MENGERJAKAN => 'in_progress',
                UjianAttempt::STATUS_SELESAI => 'submitted',
                UjianAttempt::STATUS_WAKTU_HABIS => 'expired',
            ];

            $nilai = $this->scoringService->skorPersen($attempt->skor_total, $attempt->skor_maksimal);

            if (in_array($attempt->status, UjianAttempt::STATUS_TERKUNCI, true)) {
                $skorList[] = $nilai;
            }

            $totalBenar = $attempt->jawaban()->where('is_benar', true)->count();
            $totalSalah = $attempt->jawaban()->where('is_benar', false)->count();

            $durasi = '-';
            if ($attempt->waktu_mulai && $attempt->waktu_submit) {
                $durasi = $attempt->waktu_mulai->diffForHumans($attempt->waktu_submit, true);
            }

            $selesaiItems[] = [
                'id' => $attempt->id,
                'siswa' => [
                    'id' => $siswa->id,
                    'nama_lengkap' => $siswa->user?->nama_lengkap ?? $siswa->nis,
                    'nis' => $siswa->nis,
                    'nisn' => $siswa->nisn,
                ],
                'status' => $statusMap[$attempt->status] ?? 'in_progress',
                'waktu_mulai' => $attempt->waktu_mulai?->format('d/m/Y H:i'),
                'waktu_selesai' => $attempt->waktu_submit?->format('d/m/Y H:i'),
                'durasi_pengerjaan' => $durasi,
                'total_soal' => (int) $ujian->total_soal,
                'total_benar' => $totalBenar,
                'total_salah' => $totalSalah,
                'nilai' => $nilai,
                'tab_switches_count' => (int) $attempt->tab_switch_count,
                'detail_url' => route('guru.ujian.attempt.show', [$kelasMapel, $ujian, $attempt]),
            ];
        }

        return Inertia::render('Guru/Ujian/Hasil', [
            'kelasMapel' => [
                'id' => $kelasMapel->id,
                'kelas' => $kelasMapel->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $kelasMapel->mataPelajaran?->nama_mapel ?? '-',
            ],
            'ujian' => [
                'id' => $ujian->id,
                'judul' => $ujian->judul,
                'deskripsi' => $ujian->deskripsi,
                'durasi_menit' => $ujian->durasi_menit,
                'kategori_nilai' => $ujian->kategori_nilai,
                'total_soal' => (int) $ujian->total_soal,
            ],
            'stats' => [
                'total_peserta' => $siswaAktif->count(),
                'sudah_mengerjakan' => count($selesaiItems),
                'belum_mengerjakan' => count($belumItems),
                'rata_rata' => count($skorList) > 0 ? round(array_sum($skorList) / count($skorList), 2) : 0,
                'tertinggi' => count($skorList) > 0 ? max($skorList) : 0,
                'terendah' => count($skorList) > 0 ? min($skorList) : 0,
            ],
            'attempts' => $selesaiItems,
            'belumMengerjakan' => $belumItems,
            'exportExcelUrl' => route('guru.ujian.hasil.export.excel', [$kelasMapel, $ujian]),
            'exportPdfUrl' => route('guru.ujian.hasil.export.pdf', [$kelasMapel, $ujian]),
        ]);
    }

    public function detailJawaban(KelasMapel $kelasMapel, Ujian $ujian, UjianAttempt $attempt): Response
    {
        $this->authorize('mengajar', $kelasMapel);
        $this->ensureUjianBelongsToKelasMapel($ujian, $kelasMapel);
        $this->ensureAttemptBelongsToUjian($attempt, $ujian);

        $attempt->loadMissing(['siswa.user', 'jawaban.ujianSoal.soalBank.opsi']);

        $urutanSoalIds = $attempt->urutan_soal_ids ?? [];
        $jawabanMap = $attempt->jawaban->keyBy('ujian_soal_id');

        $jawabanPayload = [];
        foreach ($urutanSoalIds as $ujianSoalId) {
            $jawaban = $jawabanMap->get($ujianSoalId);
            if (! $jawaban || ! $jawaban->ujianSoal || ! $jawaban->ujianSoal->soalBank) {
                continue;
            }

            $soalBank = $jawaban->ujianSoal->soalBank;

            $jawabanPayload[] = [
                'jawaban_id' => $jawaban->id,
                'ujian_soal_id' => $ujianSoalId,
                'pertanyaan' => $soalBank->pertanyaan,
                'topik' => $soalBank->topik,
                'kesulitan' => $soalBank->kesulitan,
                'poin_maksimal' => (float) $jawaban->ujianSoal->poin,
                'poin_didapat' => $jawaban->poin_didapat !== null ? (float) $jawaban->poin_didapat : null,
                'is_benar' => $jawaban->is_benar,
                'soal_bank_opsi_id' => $jawaban->soal_bank_opsi_id,
                'dijawab_pada' => $jawaban->dijawab_pada?->format('d/m/Y H:i:s'),
                'opsi' => $soalBank->opsi->sortBy('urutan')->map(fn ($opsi) => [
                    'id' => $opsi->id,
                    'teks_opsi' => $opsi->teks_opsi,
                    'is_benar' => (bool) $opsi->is_benar,
                ])->values(),
            ];
        }

        $skor100 = $attempt->skor_maksimal > 0
            ? $this->scoringService->skorPersen($attempt->skor_total, $attempt->skor_maksimal)
            : null;

        return Inertia::render('Guru/Ujian/AttemptDetail', [
            'kelasMapel' => [
                'id' => $kelasMapel->id,
                'kelas' => $kelasMapel->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $kelasMapel->mataPelajaran?->nama_mapel ?? '-',
            ],
            'ujian' => [
                'id' => $ujian->id,
                'judul' => $ujian->judul,
                'kategori_nilai' => $ujian->kategori_nilai,
            ],
            'attempt' => [
                'id' => $attempt->id,
                'siswa' => [
                    'nis' => $attempt->siswa?->nis ?? '-',
                    'nama' => $attempt->siswa?->user?->nama_lengkap ?? '-',
                ],
                'status' => $attempt->status,
                'skor_total' => $attempt->skor_total !== null ? (float) $attempt->skor_total : null,
                'skor_maksimal' => $attempt->skor_maksimal !== null ? (float) $attempt->skor_maksimal : null,
                'skor_100' => $skor100,
                'waktu_mulai' => $attempt->waktu_mulai?->format('d/m/Y H:i:s'),
                'waktu_submit' => $attempt->waktu_submit?->format('d/m/Y H:i:s'),
                'tab_switch_count' => (int) $attempt->tab_switch_count,
                'tab_switch_log' => $attempt->tab_switch_log ?? [],
            ],
            'jawaban' => $jawabanPayload,
            'backUrl' => route('guru.ujian.hasil', [$kelasMapel, $ujian]),
        ]);
    }

    private function renderBuilder(KelasMapel $kelasMapel, ?Ujian $ujian): Response
    {
        $soalBank = SoalBank::with('opsi')
            ->where('guru_id', Auth::id())
            ->orderByDesc('id')
            ->get();

        $topics = $soalBank->pluck('topik')->filter()->unique()->values();

        $hasActiveAttempts = $ujian ? $this->ujianService->hasActiveAttempts($ujian) : false;

        $ujianData = null;
        if ($ujian) {
            $ujian->loadMissing('ujianSoal.soalBank');

            $ujianData = [
                'id' => $ujian->id,
                'judul' => $ujian->judul,
                'deskripsi' => $ujian->deskripsi,
                'durasi_menit' => $ujian->durasi_menit,
                'kategori_nilai' => $ujian->kategori_nilai,
                'waktu_mulai' => $ujian->waktu_mulai?->format('Y-m-d\TH:i'),
                'waktu_selesai' => $ujian->waktu_selesai?->format('Y-m-d\TH:i'),
                'acak_soal' => $ujian->acak_soal,
                'acak_opsi' => $ujian->acak_opsi,
                'soal' => $ujian->ujianSoal->sortBy('urutan')->map(fn (UjianSoal $item) => [
                    'id' => $item->id,
                    'soal_bank_id' => $item->soal_bank_id,
                    'poin' => (float) $item->poin,
                    'urutan' => $item->urutan,
                    'pertanyaan' => $item->soalBank?->pertanyaan,
                    'topik' => $item->soalBank?->topik,
                    'kesulitan' => $item->soalBank?->kesulitan,
                ])->values(),
            ];
        }

        return Inertia::render('Guru/Ujian/Builder', [
            'kelasMapel' => [
                'id' => $kelasMapel->id,
                'kelas' => $kelasMapel->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $kelasMapel->mataPelajaran?->nama_mapel ?? '-',
                'mapel_id' => $kelasMapel->mapel_id,
            ],
            'ujian' => $ujianData,
            'soalBank' => $soalBank->map(fn (SoalBank $item) => [
                'id' => $item->id,
                'mapel_id' => $item->mapel_id,
                'mapel_nama' => $item->mapel?->nama_mapel,
                'pertanyaan' => $item->pertanyaan,
                'topik' => $item->topik,
                'kesulitan' => $item->kesulitan,
                'kategori_nilai' => $item->kategori_nilai,
                'opsi_count' => $item->opsi->count(),
            ])->values(),
            'topics' => $topics,
            'submitUrl' => $ujian
                ? route('guru.ujian.update', [$kelasMapel, $ujian])
                : route('guru.ujian.store', $kelasMapel),
            'isEdit' => (bool) $ujian,
            'hasActiveAttempts' => $hasActiveAttempts,
        ]);
    }

    private function ensureUjianBelongsToKelasMapel(Ujian $ujian, KelasMapel $kelasMapel): void
    {
        abort_unless((int) $ujian->kelas_mapel_id === (int) $kelasMapel->id, 403);
    }

    private function ensureAttemptBelongsToUjian(UjianAttempt $attempt, Ujian $ujian): void
    {
        abort_unless((int) $attempt->ujian_id === (int) $ujian->id, 403);
    }
}
