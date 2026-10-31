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
        $kelasMapel = $kurikulum->kelasMapel()
            ->where('kelas_mapel.id', $request->integer('kelas_mapel_id'))
            ->where('kelas_mapel.guru_id', Auth::id())
            ->with(['kelas', 'mataPelajaran'])
            ->firstOrFail();
        $package = $this->packageService->create($kurikulum, $kelasMapel);

        return response()->download($package['path'], $package['filename'])->deleteFileAfterSend(true);
    }
}
