<?php

namespace App\Services;

use App\Models\KelasMapel;
use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PengumumanService
{
    /**
     * Validasi dan normalisasi target kelas_mapel dari data yang sudah divalidasi request,
     * termasuk penegakan scope guru (guru hanya boleh menargetkan kelas yang ia ajar).
     */
    public function prepareTarget(array $v, User $actor): array
    {
        if ($v['target'] === 'kelas_mapel') {
            $ids = collect($v['target_kelas_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
            if ($ids->isEmpty()) {
                throw ValidationException::withMessages(['target_kelas_ids' => 'Pilih minimal satu kelas tujuan.']);
            }
            if ($actor->isGuru()) {
                $allowed = KelasMapel::where('guru_id', $actor->id)->whereIn('kelas_id', $ids)->pluck('kelas_id')->unique();
                abort_unless($ids->diff($allowed)->isEmpty(), 403);
            }
            $v['target_kelas'] = $ids->map(fn ($id) => (string) $id)->values()->toJson();
            $v['kelas_mapel_id'] = KelasMapel::whereIn('kelas_id', $ids)->value('id');
        } else {
            $v['target_kelas'] = null;
            $v['kelas_mapel_id'] = null;
        }
        unset($v['target_kelas_ids'], $v['public_file'], $v['remove_public_file']);

        return $v;
    }

    /**
     * Lampirkan file publik dari request ke data yang akan disimpan, bila ada.
     */
    public function attachPublicFile(Request $request, array &$data, User $actor): void
    {
        if (! $request->hasFile('public_file')) {
            return;
        }

        $file = $request->file('public_file');
        $data['public_file_name'] = $file->getClientOriginalName();
        $data['public_file_path'] = $file->store('pengumuman-public/'.$actor->id, 'local');
        $data['public_file_mime'] = $file->getClientMimeType();
        $data['public_file_size'] = $file->getSize();
    }

    /**
     * Hapus file publik yang tersimpan untuk sebuah pengumuman, bila ada.
     */
    public function deletePublicFile(Pengumuman $pengumuman): void
    {
        if ($pengumuman->public_file_path) {
            Storage::disk('local')->delete($pengumuman->public_file_path);
        }
    }
}
