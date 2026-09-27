<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\GradeTugasRequest;
use App\Http\Requests\Guru\StoreBulkTugasRequest;
use App\Http\Requests\Guru\StoreTugasRequest;
use App\Models\KelasMapel;
use App\Models\PengumpulanFile;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\WhatsAppMessageLog;
use App\Services\TugasService;
use App\Services\WhatsAppService;
use App\Support\WhatsAppPhone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TugasController extends Controller
{
    public function __construct(protected TugasService $tugasService) {}

    public function index()
    {
        $kelasMapel = KelasMapel::with(['kelas', 'mataPelajaran', 'tahunAjaran'])
            ->where('guru_id', Auth::id())
            ->aktif()
            ->get();

        $totalSiswaByKelas = Siswa::whereIn('kelas_id', $kelasMapel->pluck('kelas_id')->unique())
            ->where('status', 'aktif')
            ->selectRaw('kelas_id, count(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');

        $tugas = Tugas::with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran'])
            ->whereIn('kelas_mapel_id', $kelasMapel->pluck('id'))
            ->withCount(['pengumpulan as sudah_mengumpulkan' => function ($q) {
                $q->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED);
            }])
            ->withCount(['pengumpulan as perlu_dinilai' => function ($q) {
                $q->whereIn('status', PengumpulanTugas::STATUS_PERLU_DINILAI)
                    ->whereNull('nilai');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Guru/Tugas/Index', [
            'kelasMapel' => $this->formatKelasMapelOptions($kelasMapel),
            'tugas' => $tugas->map(fn (Tugas $item) => $this->formatTugas(
                $item,
                (int) ($totalSiswaByKelas[$item->kelasMapel?->kelas_id] ?? 0)
            ))->values(),
            'storeUrl' => route('guru.tugas.store.bulk'),
        ]);
    }

    public function list(KelasMapel $kelasMapel)
    {
        $this->authorize('mengajar', $kelasMapel);

        $tugas = Tugas::where('kelas_mapel_id', $kelasMapel->id)
            ->withCount(['pengumpulan as sudah_mengumpulkan' => function ($q) use ($kelasMapel) {
                $q->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)
                    ->whereHas('siswa', fn ($siswa) => $siswa
                        ->where('kelas_id', $kelasMapel->kelas_id)
                        ->where('status', 'aktif'));
            }])
            ->withCount(['pengumpulan as perlu_dinilai' => function ($q) use ($kelasMapel) {
                $q->whereIn('status', PengumpulanTugas::STATUS_PERLU_DINILAI)
                    ->whereNull('nilai')
                    ->whereHas('siswa', fn ($siswa) => $siswa
                        ->where('kelas_id', $kelasMapel->kelas_id)
                        ->where('status', 'aktif'));
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        $totalSiswa = Siswa::where('kelas_id', $kelasMapel->kelas_id)
            ->where('status', 'aktif')
            ->count();

        return Inertia::render('Guru/Tugas/List', [
            'kelasMapel' => [
                'id' => $kelasMapel->id,
                'kelas' => $kelasMapel->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $kelasMapel->mataPelajaran?->nama_mapel ?? '-',
                'store_url' => route('guru.tugas.store', $kelasMapel),
                'export_excel_url' => route('guru.tugas.export.excel', $kelasMapel),
                'export_pdf_url' => route('guru.tugas.export.pdf', $kelasMapel),
                'workspace_url' => route('guru.kelas-mapel.show', $kelasMapel),
                'back_url' => route('guru.tugas.index'),
            ],
            'tugas' => $tugas->map(fn (Tugas $item) => [
                'id' => $item->id,
                'judul' => $item->judul,
                'deskripsi' => Str::limit((string) $item->deskripsi, 80),
                'batas_waktu' => $item->batas_waktu?->format('d M Y'),
                'sudah_mengumpulkan' => $item->sudah_mengumpulkan ?? 0,
                'perlu_dinilai' => $item->perlu_dinilai ?? 0,
                'progress_percent' => $totalSiswa > 0 ? round((($item->sudah_mengumpulkan ?? 0) / $totalSiswa) * 100) : 0,
                'is_overdue' => $item->batas_waktu ? now()->startOfDay()->gt($item->batas_waktu->copy()->startOfDay()) : false,
                'pengumpulan_url' => route('guru.tugas.pengumpulan', [$kelasMapel, $item]),
                'delete_url' => route('guru.tugas.destroy', $item),
            ])->values(),
            'totalSiswa' => $totalSiswa,
        ]);
    }

    public function store(StoreTugasRequest $request, KelasMapel $kelasMapel)
    {
        $this->authorize('mengajar', $kelasMapel);

        $this->tugasService->createTugas($kelasMapel, $request->validated());

        return redirect()->route('guru.tugas.list', $kelasMapel)
            ->with('success', 'Tugas berhasil ditambahkan.');
    }

    public function storeBulk(StoreBulkTugasRequest $request)
    {
        $validated = $request->validated();

        $kelasMapel = $this->assignedKelasMapelQuery()
            ->whereIn('id', $validated['kelas_mapel_ids'])
            ->get();

        if ($kelasMapel->count() !== count(array_unique($validated['kelas_mapel_ids']))) {
            return back()->withInput()->with('error', 'Pilihan kelas tidak valid.');
        }

        $this->tugasService->createBulkTugas($kelasMapel, $validated);

        return redirect()->route('guru.tugas.index')
            ->with('success', 'Tugas berhasil ditambahkan ke kelas yang dipilih.');
    }

    public function pengumpulan(KelasMapel $kelasMapel, Tugas $tugas)
    {
        $this->authorize('mengajar', $kelasMapel);
        $this->ensureTugasBelongsToKelasMapel($tugas, $kelasMapel);
        $kelasMapel->loadMissing(['kelas', 'mataPelajaran']);

        $pengumpulan = PengumpulanTugas::with(['siswa.user', 'files'])
            ->where('tugas_id', $tugas->id)
            ->get()
            ->keyBy('siswa_id');

        $siswa = Siswa::with('user')
            ->where('kelas_id', $kelasMapel->kelas_id)
            ->where('status', 'aktif')
            ->orderBy('nis')
            ->get();
        $lastWhatsAppByStudent = WhatsAppMessageLog::query()
            ->where('guru_id', Auth::id())
            ->whereIn('siswa_id', $siswa->pluck('id'))
            ->orderByDesc('prepared_at')
            ->get()
            ->unique('siswa_id')
            ->keyBy('siswa_id');

        $missingSubmittedStudentIds = $pengumpulan->keys()
            ->diff($siswa->pluck('id'))
            ->values();

        if ($missingSubmittedStudentIds->isNotEmpty()) {
            $submittedStudents = Siswa::with('user')
                ->whereIn('id', $missingSubmittedStudentIds)
                ->orderBy('nis')
                ->get();

            $siswa = $siswa
                ->concat($submittedStudents)
                ->sortBy('nis', SORT_NATURAL)
                ->values();
        }

        return Inertia::render('Guru/Tugas/Pengumpulan', [
            'kelasMapel' => [
                'id' => $kelasMapel->id,
                'kelas' => $kelasMapel->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $kelasMapel->mataPelajaran?->nama_mapel ?? '-',
                'back_url' => route('guru.tugas.list', $kelasMapel),
                'workspace_url' => route('guru.kelas-mapel.show', $kelasMapel),
                'export_excel_url' => route('guru.tugas.pengumpulan.export.excel', [$kelasMapel, $tugas]),
                'export_pdf_url' => route('guru.tugas.pengumpulan.export.pdf', [$kelasMapel, $tugas]),
            ],
            'tugas' => [
                'id' => $tugas->id,
                'judul' => $tugas->judul,
                'deskripsi' => $tugas->deskripsi,
                'batas_waktu' => $tugas->batas_waktu?->format('d/m/Y'),
            ],
            'pengumpulan' => $siswa->map(function (Siswa $student, int $index) use ($pengumpulan, $kelasMapel, $tugas, $lastWhatsAppByStudent) {
                $item = $pengumpulan->get($student->id);
                $daysLate = $this->tugasService->lateDays($item?->tanggal_kumpul, $tugas->batas_waktu);
                // lateDays() returns 0 when there is no submission timestamp (by
                // design, to avoid over-penalizing), so it cannot gate the reminder
                // button on its own. A reminder remains available for every overdue
                // row that has not been validly submitted yet, including rows with
                // no submission record at all.
                $notSubmitted = $item === null || $item->status === PengumpulanTugas::STATUS_BELUM;
                $canPrepareWhatsApp = $tugas->batas_waktu?->isPast() && ($daysLate > 0 || $notSubmitted);
                $lastWhatsApp = $lastWhatsAppByStudent->get($student->id);

                return [
                    'id' => $item?->id,
                    'key' => $item?->id ? 'pengumpulan-'.$item->id : 'siswa-'.$student->id,
                    'no' => $index + 1,
                    'siswa' => $student->user?->nama_lengkap ?: ($student->user?->username ?: $student->nis),
                    'nis' => $student->nis,
                    'status' => ($daysLate > 0 && in_array($item?->status, ['sudah', 'dinilai'], true)) ? 'terlambat' : ($item?->status ?? 'belum'),
                    'tanggal_kumpul' => $item?->tanggal_kumpul?->format('d/m/Y H:i'),
                    'hari_terlambat' => $daysLate,
                    'penalty_perkiraan' => $this->tugasService->latePenaltyPoints($item, $tugas),
                    'teks_jawaban' => $item?->teks_jawaban,
                    'catatan' => $item?->catatan,
                    'nilai' => $item?->nilai,
                    'nilai_input' => $item?->nilai_sebelum_penalty ?? $item?->nilai,
                    'penalty_terlambat' => $item?->penalty_terlambat ?? 0,
                    'nilai_url' => route('guru.tugas.nilai', [$kelasMapel, $tugas, $student]),
                    'whatsapp_url' => $canPrepareWhatsApp ? route('guru.tugas.whatsapp', [$kelasMapel, $tugas, $student]) : null,
                    'whatsapp_last_sent_at' => $lastWhatsApp?->sent_marked_at?->format('d/m/Y H:i'),
                    'whatsapp_last_error' => $lastWhatsApp?->status === 'failed' ? $lastWhatsApp?->error_message : null,
                    'legacy_file_url' => $item?->file_upload ? route('guru.tugas.pengumpulan.download', [$kelasMapel, $tugas, $item]) : null,
                    'files' => $item?->files->map(fn (PengumpulanFile $file) => [
                        'id' => $file->id,
                        'name' => $file->file_name,
                        'url' => route('guru.tugas.file.download', [$kelasMapel, $tugas, $file]),
                    ])->values()->all() ?? [],
                ];
            })->values()->all(),
        ]);
    }

    public function nilai(GradeTugasRequest $request, KelasMapel $kelasMapel, Tugas $tugas, Siswa $siswa)
    {
        $this->authorize('mengajar', $kelasMapel);
        $this->ensureTugasBelongsToKelasMapel($tugas, $kelasMapel);
        $this->ensureSiswaBelongsToKelasMapel($siswa, $kelasMapel);

        $result = $this->tugasService->gradeSubmission($kelasMapel, $tugas, $siswa, $request->validated());

        if ($result['status'] === 'no_change') {
            if ($request->expectsJson()) {
                return response()->json(['message' => $result['message']], 422);
            }

            return back()->with('info', $result['message']);
        }

        $savedPengumpulan = $result['pengumpulan'];

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $result['message'],
                'id' => $savedPengumpulan->id,
                'status' => $savedPengumpulan->status,
                'nilai' => $savedPengumpulan->nilai,
                'nilai_input' => $savedPengumpulan->nilai_sebelum_penalty ?? $savedPengumpulan->nilai,
                'catatan' => $savedPengumpulan->catatan,
                'penalty_terlambat' => $savedPengumpulan->penalty_terlambat,
                'graded_at' => $savedPengumpulan->graded_at?->format('H:i'),
                'saved_at' => now()->format('H:i'),
            ]);
        }

        return back()->with('success', $result['message']);
    }

    public function whatsapp(KelasMapel $kelasMapel, Tugas $tugas, Siswa $siswa, WhatsAppService $service)
    {
        $this->authorize('mengajar', $kelasMapel);
        $this->ensureTugasBelongsToKelasMapel($tugas, $kelasMapel);
        $this->ensureSiswaBelongsToKelasMapel($siswa, $kelasMapel);
        $normalizedPhone = WhatsAppPhone::normalize((string) $siswa->nomor_whatsapp);
        abort_unless($normalizedPhone !== null, 422, 'Nomor WhatsApp siswa belum valid. Periksa format nomor di pengaturan siswa.');
        abort_unless($siswa->whatsapp_opt_in, 422, 'Siswa belum menyetujui menerima informasi melalui WhatsApp.');
        if ($siswa->nomor_whatsapp !== $normalizedPhone) {
            $siswa->update(['nomor_whatsapp' => $normalizedPhone]);
        }

        $prepared = $service->prepareLateTaskMessage($siswa, $tugas, (int) Auth::id());
        $result = $service->sendPreparedMessage($prepared['log_id'], $normalizedPhone, $prepared['message']);

        return response()->json($result);
    }

    public function downloadFile(KelasMapel $kelasMapel, Tugas $tugas, PengumpulanFile $file)
    {
        $this->authorize('mengajar', $kelasMapel);
        $this->ensureTugasBelongsToKelasMapel($tugas, $kelasMapel);
        $file->loadMissing('pengumpulan');
        abort_unless($file->pengumpulan, 404);
        $this->ensurePengumpulanBelongsToTugas($file->pengumpulan, $tugas);

        return $this->downloadPengumpulanPath($file->file_path, $file->file_name);
    }

    public function downloadLegacyFile(KelasMapel $kelasMapel, Tugas $tugas, PengumpulanTugas $pengumpulan)
    {
        $this->authorize('mengajar', $kelasMapel);
        $this->ensureTugasBelongsToKelasMapel($tugas, $kelasMapel);
        $this->ensurePengumpulanBelongsToTugas($pengumpulan, $tugas);

        return $this->downloadPengumpulanPath($pengumpulan->file_upload, basename((string) $pengumpulan->file_upload));
    }

    public function destroy(Tugas $tugas)
    {
        $kelasMapel = $tugas->kelasMapel;
        $this->authorize('mengajar', $kelasMapel);

        if ($tugas->pengumpulan()->exists()) {
            return back()->with('error', 'Tugas tidak dapat dihapus karena sudah memiliki pengumpulan siswa.');
        }

        $tugas->delete();

        return back()
            ->with('success', 'Tugas berhasil dihapus.');
    }

    private function ensureTugasBelongsToKelasMapel(Tugas $tugas, KelasMapel $kelasMapel): void
    {
        abort_unless((int) $tugas->kelas_mapel_id === (int) $kelasMapel->id, 403);
    }

    private function ensurePengumpulanBelongsToTugas(PengumpulanTugas $pengumpulan, Tugas $tugas): void
    {
        abort_unless((int) $pengumpulan->tugas_id === (int) $tugas->id, 403);
    }

    private function ensureSiswaBelongsToKelasMapel(Siswa $siswa, KelasMapel $kelasMapel): void
    {
        abort_unless(
            (int) $siswa->kelas_id === (int) $kelasMapel->kelas_id && $siswa->status === 'aktif',
            403
        );
    }

    private function downloadPengumpulanPath(?string $path, string $downloadName)
    {
        abort_unless($path, 404);

        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->download($disk->path($path), $downloadName);
    }

    private function assignedKelasMapelQuery()
    {
        return KelasMapel::with(['kelas', 'mataPelajaran', 'tahunAjaran'])
            ->where('guru_id', Auth::id())
            ->aktif();
    }

    private function formatKelasMapelOptions($kelasMapel)
    {
        return $kelasMapel->map(fn (KelasMapel $item) => [
            'id' => $item->id,
            'kelas' => $item->kelas?->displayName() ?? '-',
            'mata_pelajaran' => $item->mataPelajaran?->nama_mapel ?? '-',
            'semester' => $item->semester,
            'label' => ($item->kelas?->displayName() ?? '-').' - '.($item->mataPelajaran?->nama_mapel ?? '-').' (Sem. '.$item->semester.')',
            'href' => route('guru.tugas.list', $item),
        ])->values();
    }

    private function formatTugas(Tugas $item, int $totalSiswa): array
    {
        $kelasMapel = $item->kelasMapel;

        return [
            'id' => $item->id,
            'kelas_mapel_id' => $item->kelas_mapel_id,
            'judul' => $item->judul,
            'deskripsi' => Str::limit((string) $item->deskripsi, 80),
            'batas_waktu' => $item->batas_waktu?->format('d M Y'),
            'sudah_mengumpulkan' => $item->sudah_mengumpulkan ?? 0,
            'perlu_dinilai' => $item->perlu_dinilai ?? 0,
            'total_siswa' => $totalSiswa,
            'progress_percent' => $totalSiswa > 0 ? round((($item->sudah_mengumpulkan ?? 0) / $totalSiswa) * 100) : 0,
            'is_overdue' => $item->batas_waktu ? now()->startOfDay()->gt($item->batas_waktu->copy()->startOfDay()) : false,
            'kelas' => $kelasMapel?->kelas?->displayName() ?? '-',
            'mata_pelajaran' => $kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            'pengumpulan_url' => $kelasMapel ? route('guru.tugas.pengumpulan', [$kelasMapel, $item]) : null,
            'delete_url' => route('guru.tugas.destroy', $item),
        ];
    }

    private function deletePengumpulanPath(?string $path): void
    {
        if (! $path) {
            return;
        }

        Storage::disk('local')->delete($path);
        Storage::disk('public')->delete($path);
    }
}
