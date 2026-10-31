<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\ExportKurikulumPackageRequest;
use App\Models\Kurikulum;
use App\Services\KurikulumPackageService;
use Illuminate\Support\Facades\Auth;

class KurikulumPackageController extends Controller
{
    public function __construct(private readonly KurikulumPackageService $packageService) {}

    public function download(ExportKurikulumPackageRequest $request, Kurikulum $kurikulum)
    {
        $this->authorize('view', $kurikulum);
        $requestedIds = collect($request->input('kelas_mapel_ids', []))
            ->when($request->filled('kelas_mapel_id'), fn ($ids) => $ids->push($request->integer('kelas_mapel_id')))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $kelasMapel = $kurikulum->kelasMapel()
            ->whereIn('kelas_mapel.id', $requestedIds)
            ->where('kelas_mapel.guru_id', Auth::id())
            ->with(['kelas', 'mataPelajaran'])
            ->get();

        if ($kelasMapel->count() !== $requestedIds->count()) {
            abort(404);
        }

        $package = $this->packageService->create($kurikulum, $kelasMapel, $request->input('format', 'both'));

        return response()->download($package['path'], $package['filename'])->deleteFileAfterSend(true);
    }
}
