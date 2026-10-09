<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\PengumpulanFile;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Services\TugasSubmissionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class TugasController extends Controller
{
    private const MAX_UPLOAD_FILES = TugasSubmissionService::MAX_UPLOAD_FILES;

    private const UPLOAD_MAX_KB = TugasSubmissionService::UPLOAD_MAX_KB;

    private const UPLOAD_TOTAL_MAX_KB = TugasSubmissionService::UPLOAD_TOTAL_MAX_KB;

    private const UPLOAD_EXTENSIONS = TugasSubmissionService::UPLOAD_EXTENSIONS;

    /**
     * Aturan validasi satu file tugas.
     * Sengaja TANPA validasi MIME/konten (mimetypes/mimes) karena tebakan
     * MIME server tidak konsisten antar perangkat dan sering menolak file
     * .jpg/.pdf yang sah walau ukurannya di bawah batas. Cukup validasi
     * ekstensi + ukuran, konsisten dengan validasi di sisi frontend.
     */
    public static function uploadFileRules(): string
    {
        return 'nullable|file|extensions:'.self::UPLOAD_EXTENSIONS.'|max:'.self::UPLOAD_MAX_KB;
    }

    public function index()
    {
        $user = Auth::user();
        $siswa = $user->siswa;

        if (! $siswa) {
            return redirect()->route('login')->with('error', 'Data siswa tidak ditemukan.');
        }

        $tugas = Tugas::with(['kelasMapel.mataPelajaran', 'pengumpulan' => function ($q) use ($siswa) {
            $q->where('siswa_id', $siswa->id);
        }])
            ->whereHas('kelasMapel', function ($q) use ($siswa) {
                $q->where('kelas_id', $siswa->kelas_id);
                $q->aktif();
            })
            ->orderBy('batas_waktu', 'desc')
            ->get();

        return Inertia::render('Siswa/Tugas/Index', [
            'tugas' => $tugas->map(function (Tugas $item) use ($siswa) {
                $pengumpulan = $item->pengumpulan->where('siswa_id', $siswa->id)->first();

                return [
                    'id' => $item->id,
                    'judul' => $item->judul,
                    'mata_pelajaran' => $item->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
                    'batas_waktu' => $item->batas_waktu ? Carbon::parse($item->batas_waktu)->format('d/m/Y') : '-',
                    'status' => $pengumpulan?->status,
                    'nilai' => $pengumpulan?->nilai ?? '-',
                    'show_url' => route('siswa.tugas.show', $item),
                    'workspace_url' => $item->kelasMapel ? route('siswa.kelas-mapel.show', $item->kelasMapel) : null,
                ];
            })->values(),
        ]);
    }

    public function show(Tugas $tugas)
    {
        $user = Auth::user();
        $siswa = $user->siswa;
        $tugas->loadMissing('kelasMapel.tahunAjaran');

        $this->ensureTugasAktifUntukSiswa($tugas, $siswa);

        $pengumpulan = PengumpulanTugas::with('files')
            ->where('tugas_id', $tugas->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        $tugas->loadMissing(['kelasMapel.mataPelajaran', 'kelasMapel.guru', 'kelasMapel.kelas']);

        return Inertia::render('Siswa/Tugas/Show', [
            'tugas' => [
                'id' => $tugas->id,
                'judul' => $tugas->judul,
                'kategori_nilai' => $tugas->kategori_nilai ?? 'NH',
                'mata_pelajaran' => $tugas->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
                'guru' => $tugas->kelasMapel?->guru?->nama_lengkap ?? '-',
                'kelas' => trim(($tugas->kelasMapel?->kelas?->tingkat ? $tugas->kelasMapel?->kelas?->tingkat.' ' : '').($tugas->kelasMapel?->kelas?->nama_kelas ?? '')),
                'batas_waktu' => $tugas->batas_waktu ? Carbon::parse($tugas->batas_waktu)->format('d M Y') : '-',
                'is_late' => $tugas->batas_waktu ? now()->gt($tugas->batas_waktu) : false,
                'deskripsi' => $tugas->deskripsi ?? 'Tidak ada deskripsi',
                'store_url' => route('siswa.tugas.kumpul', $tugas),
                'back_url' => route('siswa.tugas.index'),
            ],
            'pengumpulan' => $pengumpulan ? [
                'id' => $pengumpulan->id,
                'status' => $pengumpulan->status,
                'tanggal_kumpul' => $pengumpulan->tanggal_kumpul ? Carbon::parse($pengumpulan->tanggal_kumpul)->format('d M Y H:i') : '-',
                'nilai' => $pengumpulan->nilai,
                'teks_jawaban' => $pengumpulan->teks_jawaban,
                'catatan' => $pengumpulan->catatan,
                'legacy_file_url' => $pengumpulan->file_upload ? route('siswa.tugas.pengumpulan.download', [$tugas, $pengumpulan]) : null,
                'files' => $pengumpulan->files->map(fn (PengumpulanFile $file) => [
                    'id' => $file->id,
                    'name' => $file->file_name,
                    'url' => route('siswa.tugas.file.download', [$tugas, $file]),
                ])->values(),
            ] : null,
            'canSubmit' => ! $pengumpulan || in_array($pengumpulan->status, [
                PengumpulanTugas::STATUS_BELUM,
                PengumpulanTugas::STATUS_PERLU_PERBAIKAN,
            ]),
        ]);
    }

    public function store(Request $request, Tugas $tugas)
    {
        $user = Auth::user();
        $siswa = $user->siswa;

        $fileRules = self::uploadFileRules();
        $validated = $request->validate([
            'files' => 'nullable|array|max:'.self::MAX_UPLOAD_FILES,
            'file_upload' => $fileRules,
            'files.*' => $fileRules,
            'teks_jawaban' => 'nullable|string|max:5000',
        ], [
            'file_upload.file' => 'Upload harus berupa file.',
            'file_upload.extensions' => 'Ekstensi file harus .jpg, .jpeg, atau .pdf.',
            'file_upload.max' => 'Ukuran file maksimal 5MB.',
            'files.array' => 'Upload file tidak valid. Silakan pilih file ulang.',
            'files.max' => 'Maksimal '.self::MAX_UPLOAD_FILES.' file untuk satu pengumpulan tugas.',
            'files.*.file' => 'Upload harus berupa file.',
            'files.*.extensions' => 'Ekstensi file harus .jpg, .jpeg, atau .pdf.',
            'files.*.max' => 'Ukuran setiap file maksimal 5MB.',
        ]);

        $tugas->loadMissing('kelasMapel.tahunAjaran');

        $this->ensureTugasAktifUntukSiswa($tugas, $siswa);

        $result = app(TugasSubmissionService::class)->submit($tugas, $siswa, $user, $request, $validated);

        return match ($result['status']) {
            'validation' => back()->withInput()->withErrors([$result['field'] => $result['message']]),
            'conflict' => back()->with('error', $result['message']),
            'failed' => back()->withInput()->with('error', $result['message']),
            'success' => redirect()->route('siswa.tugas.show', $tugas)->with('success', 'Tugas berhasil dikumpulkan.'),
        };
    }

    public function downloadFile(Tugas $tugas, PengumpulanFile $file)
    {
        $user = Auth::user();
        $siswa = $user->siswa;
        $tugas->loadMissing('kelasMapel.tahunAjaran');
        $this->ensureTugasAktifUntukSiswa($tugas, $siswa);
        $file->loadMissing('pengumpulan');
        abort_unless($file->pengumpulan, 404);
        $this->ensurePengumpulanMilikSiswaDanTugas($file->pengumpulan, $tugas, $siswa);

        return $this->downloadPengumpulanPath($file->file_path, $file->file_name);
    }

    public function downloadLegacyFile(Tugas $tugas, PengumpulanTugas $pengumpulan)
    {
        $user = Auth::user();
        $siswa = $user->siswa;
        $tugas->loadMissing('kelasMapel.tahunAjaran');
        $this->ensureTugasAktifUntukSiswa($tugas, $siswa);
        $this->ensurePengumpulanMilikSiswaDanTugas($pengumpulan, $tugas, $siswa);

        return $this->downloadPengumpulanPath($pengumpulan->file_upload, basename((string) $pengumpulan->file_upload));
    }

    private function ensureTugasAktifUntukSiswa(Tugas $tugas, ?Siswa $siswa): void
    {
        abort_unless($siswa && $tugas->kelasMapel && (int) $siswa->kelas_id === (int) $tugas->kelasMapel->kelas_id && $tugas->kelasMapel->isAktif(), 403, 'Anda tidak memiliki akses ke tugas ini.');
    }

    private function ensurePengumpulanMilikSiswaDanTugas(PengumpulanTugas $pengumpulan, Tugas $tugas, ?Siswa $siswa): void
    {
        abort_unless($siswa && (int) $pengumpulan->siswa_id === (int) $siswa->id && (int) $pengumpulan->tugas_id === (int) $tugas->id, 403);
    }

    private function downloadPengumpulanPath(?string $path, string $downloadName)
    {
        abort_unless($path, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);

        return response()->download($disk->path($path), basename($downloadName));
    }
}
