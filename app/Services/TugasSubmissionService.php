<?php

namespace App\Services;

use App\Models\PengumpulanFile;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Menangani logika pengumpulan tugas oleh siswa: validasi jumlah/ukuran file,
 * penyimpanan pengumpulan dengan penguncian baris untuk mencegah duplikasi
 * pada request bersamaan, serta pembersihan file yatim bila terjadi kegagalan.
 */
class TugasSubmissionService
{
    public const MAX_UPLOAD_FILES = 5;

    public const UPLOAD_MAX_KB = 5120;

    public const UPLOAD_TOTAL_MAX_KB = 20480;

    public const UPLOAD_EXTENSIONS = 'jpg,jpeg,pdf';

    public function __construct(
        protected NotifikasiService $notifikasiService,
    ) {}

    /**
     * Proses pengumpulan tugas dari siswa.
     *
     * @return array{status: 'validation'|'conflict'|'failed'|'success', field?: string, message?: string, pengumpulan?: PengumpulanTugas}
     */
    public function submit(Tugas $tugas, Siswa $siswa, User $user, Request $request, array $validated): array
    {
        $hasTextJawaban = filled($validated['teks_jawaban'] ?? null);
        $hasSingleFile = $request->hasFile('file_upload');
        $multipleFiles = collect($request->file('files', []))->filter();
        $hasMultipleFiles = $multipleFiles->isNotEmpty();
        $totalUploadedFiles = ($hasSingleFile ? 1 : 0) + $multipleFiles->count();

        if (! $hasTextJawaban && ! $hasSingleFile && ! $hasMultipleFiles) {
            return ['status' => 'validation', 'field' => 'file_upload', 'message' => 'Upload file atau isi jawaban teks terlebih dahulu.'];
        }

        if ($totalUploadedFiles > self::MAX_UPLOAD_FILES) {
            return ['status' => 'validation', 'field' => 'files', 'message' => 'Maksimal '.self::MAX_UPLOAD_FILES.' file untuk satu pengumpulan tugas.'];
        }

        $filesToStore = collect($hasSingleFile ? [$request->file('file_upload')] : [])->merge($multipleFiles);

        $totalUploadBytes = $filesToStore->sum(fn ($file) => (int) $file->getSize());

        if ($totalUploadBytes > self::UPLOAD_TOTAL_MAX_KB * 1024) {
            $limitMb = (int) (self::UPLOAD_TOTAL_MAX_KB / 1024);

            return ['status' => 'validation', 'field' => 'files', 'message' => 'Total ukuran file melebihi batas maksimal '.$limitMb.'MB.'];
        }

        $existingPengumpulan = PengumpulanTugas::where('tugas_id', $tugas->id)
            ->where('siswa_id', $siswa->id)
            ->first();

        if ($existingPengumpulan && ! in_array($existingPengumpulan->status, [
            PengumpulanTugas::STATUS_BELUM,
            PengumpulanTugas::STATUS_PERLU_PERBAIKAN,
        ])) {
            return ['status' => 'conflict', 'message' => 'Tugas ini sudah dikumpulkan dan tidak dapat diubah.'];
        }

        $statusPengumpulan = $tugas->batas_waktu && now()->gt($tugas->batas_waktu)
            ? PengumpulanTugas::STATUS_TERLAMBAT
            : PengumpulanTugas::STATUS_SUDAH;
        $uploadedFiles = [];
        $storedPaths = [];

        try {
            DB::beginTransaction();
            // Re-check the submission while holding the row lock. The check
            // above is only an early response; concurrent requests must not
            // both pass it and append duplicate files.
            $lockedPengumpulan = PengumpulanTugas::where('tugas_id', $tugas->id)
                ->where('siswa_id', $siswa->id)
                ->lockForUpdate()
                ->first();
            if ($lockedPengumpulan && ! in_array($lockedPengumpulan->status, [
                PengumpulanTugas::STATUS_BELUM,
                PengumpulanTugas::STATUS_PERLU_PERBAIKAN,
            ], true)) {
                throw new \RuntimeException('Tugas ini sudah dikumpulkan dan tidak dapat diubah.');
            }

            $pengumpulan = PengumpulanTugas::updateOrCreate(
                ['tugas_id' => $tugas->id, 'siswa_id' => $siswa->id],
                ['status' => $statusPengumpulan, 'file_upload' => null, 'teks_jawaban' => $validated['teks_jawaban'] ?? null, 'tanggal_kumpul' => now(), 'graded_at' => null]
            );

            foreach ($filesToStore as $file) {
                $path = $file->store('tugas/'.$tugas->id.'/'.$siswa->id, 'local');
                $storedPaths[] = $path;
                $uploadedFiles[] = ['pengumpulan_id' => $pengumpulan->id, 'file_name' => $file->getClientOriginalName(), 'file_path' => $path, 'uploaded_at' => now()];
            }

            if (count($uploadedFiles) > 0) {
                PengumpulanFile::insert($uploadedFiles);
                $pengumpulan->update(['file_upload' => $uploadedFiles[0]['file_path']]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            report($e);

            return ['status' => 'failed', 'message' => 'Tugas gagal dikumpulkan. Silakan coba lagi.'];
        }

        $guruId = $tugas->kelasMapel->guru_id;
        $this->notifikasiService->notifikasiUser(
            $guruId,
            'kumpul_tugas',
            'Siswa mengumpulkan tugas',
            "{$user->nama_lengkap} telah mengumpulkan tugas '{$tugas->judul}'.",
            route('guru.tugas.pengumpulan', [$tugas->kelas_mapel_id, $tugas->id])
        );

        return ['status' => 'success', 'pengumpulan' => $pengumpulan];
    }
}
