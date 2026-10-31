<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\GeneratePromesRequest;
use App\Http\Requests\Guru\UpdateRencanaPertemuanRequest;
use App\Models\KelasMapel;
use App\Models\Kurikulum;
use App\Models\RencanaPertemuan;
use App\Services\PromesService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PromesController extends Controller
{
    public function __construct(private PromesService $promesService) {}

    public function index(Kurikulum $kurikulum)
    {
        $this->authorize('view', $kurikulum);
        $kurikulum->load([
            'mataPelajaran',
            'tahunAjaran',
            'kelasMapel.kelas',
            'kelasMapel.mataPelajaran',
            'tujuanPembelajaran' => fn ($query) => $query->orderBy('urutan')->orderBy('id'),
        ]);

        $rencana = RencanaPertemuan::query()
            ->with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'tujuanPembelajaran'])
            ->where('kurikulum_id', $kurikulum->id)
            ->orderBy('tanggal_rencana')
            ->orderBy('kelas_mapel_id')
            ->orderBy('pertemuan_ke')
            ->get();
        $period = $this->promesService->defaultPeriod($kurikulum);

        return Inertia::render('Guru/Kurikulum/Promes', [
            'kurikulum' => [
                'id' => $kurikulum->id,
                'nama' => $kurikulum->nama,
                'mata_pelajaran' => $kurikulum->mataPelajaran?->nama_mapel ?? '-',
                'tahun_ajaran' => $kurikulum->tahunAjaran?->tahun ?? '-',
                'semester' => (string) $kurikulum->semester,
                'kelas_mapel' => $kurikulum->kelasMapel->map(fn (KelasMapel $item) => [
                    'id' => $item->id,
                    'label' => ($item->kelas?->displayName() ?? '-').' — '.($item->mataPelajaran?->nama_mapel ?? '-'),
                    'kelas' => $item->kelas?->displayName() ?? '-',
                    'rencana_count' => $rencana->where('kelas_mapel_id', $item->id)->count(),
                ])->values(),
                'tujuan_pembelajaran' => $kurikulum->tujuanPembelajaran->map(fn ($tp) => [
                    'id' => $tp->id,
                    'kode' => $tp->kode,
                    'urutan' => $tp->urutan,
                    'tujuan' => $tp->tujuan,
                    'alokasi_jp' => $tp->alokasi_jp,
                ])->values(),
            ],
            'rencana' => $rencana->map(fn (RencanaPertemuan $item) => $this->formatRencana($item))->values(),
            'summary' => [
                'total' => $rencana->count(),
                'jp' => (int) $rencana->sum('jp'),
                'direncanakan' => $rencana->where('status', RencanaPertemuan::STATUS_DIRENCANAKAN)->count(),
                'terlaksana' => $rencana->where('status', RencanaPertemuan::STATUS_TERLAKSANA)->count(),
            ],
            'defaultPeriod' => $period,
            'generateUrl' => route('guru.kurikulum.promes.generate', $kurikulum),
            'packageUrl' => route('guru.kurikulum.package.download', $kurikulum),
        ]);
    }

    public function generate(GeneratePromesRequest $request, Kurikulum $kurikulum)
    {
        $validated = $request->validated();
        $kelasMapel = $this->resolveKelasMapel($kurikulum, $validated['kelas_mapel_ids']);
        $existingQuery = RencanaPertemuan::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->whereIn('kelas_mapel_id', $kelasMapel->modelKeys());

        if ($existingQuery->where('status', '!=', RencanaPertemuan::STATUS_DIRENCANAKAN)->exists()) {
            throw ValidationException::withMessages([
                'rencana' => 'Sebagian rencana sudah memiliki status terlaksana, ditunda, atau digeser sehingga tidak dapat dibuat ulang.',
            ]);
        }

        if ($existingQuery->exists() && ! $request->boolean('replace')) {
            throw ValidationException::withMessages([
                'rencana' => 'Rencana pertemuan sudah ada. Aktifkan pilihan ganti rencana draft untuk membuat ulang.',
            ]);
        }

        $result = DB::transaction(function () use ($existingQuery, $kurikulum, $kelasMapel, $validated) {
            $existingQuery->where('status', RencanaPertemuan::STATUS_DIRENCANAKAN)->delete();

            return $this->promesService->generate(
                $kurikulum,
                $kelasMapel,
                Carbon::parse($validated['start_date']),
                Carbon::parse($validated['end_date']),
                (int) $validated['jp_per_pertemuan'],
            );
        });

        $message = $result['created'].' rencana pertemuan berhasil dibuat.';
        if ($result['unallocated_jp'] > 0) {
            $message .= ' Masih ada '.$result['unallocated_jp'].' JP yang belum mendapat tanggal.';
        }

        return back()->with('success', $message);
    }

    public function update(
        UpdateRencanaPertemuanRequest $request,
        Kurikulum $kurikulum,
        RencanaPertemuan $rencanaPertemuan,
    ) {
        $this->assertBelongsToKurikulum($rencanaPertemuan, $kurikulum);
        $this->authorize('update', $kurikulum);
        $rencanaPertemuan->update($request->validated());

        return back()->with('success', 'Rencana pertemuan berhasil diperbarui.');
    }

    public function destroy(Kurikulum $kurikulum, RencanaPertemuan $rencanaPertemuan)
    {
        $this->assertBelongsToKurikulum($rencanaPertemuan, $kurikulum);
        $this->authorize('update', $kurikulum);

        if ($rencanaPertemuan->status !== RencanaPertemuan::STATUS_DIRENCANAKAN) {
            throw ValidationException::withMessages([
                'rencana' => 'Rencana yang sudah diproses tidak dapat dihapus.',
            ]);
        }

        $rencanaPertemuan->delete();

        return back()->with('success', 'Rencana pertemuan berhasil dihapus.');
    }

    private function resolveKelasMapel(Kurikulum $kurikulum, array $ids)
    {
        $allowedIds = $kurikulum->kelasMapel()
            ->where('kelas_mapel.guru_id', Auth::id())
            ->pluck('kelas_mapel.id');
        $requestedIds = collect($ids)->map(fn ($id) => (int) $id)->unique()->values();

        if ($requestedIds->diff($allowedIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'kelas_mapel_ids' => 'Kelas-mapel yang dipilih tidak terhubung dengan kurikulum ini.',
            ]);
        }

        return KelasMapel::with(['kelas', 'mataPelajaran'])
            ->whereIn('id', $requestedIds)
            ->get();
    }

    private function assertBelongsToKurikulum(RencanaPertemuan $rencana, Kurikulum $kurikulum): void
    {
        abort_unless((int) $rencana->kurikulum_id === (int) $kurikulum->id, 404);
    }

    private function formatRencana(RencanaPertemuan $item): array
    {
        return [
            'id' => $item->id,
            'kelas_mapel_id' => $item->kelas_mapel_id,
            'kelas' => $item->kelasMapel?->kelas?->displayName() ?? '-',
            'mata_pelajaran' => $item->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            'pertemuan_ke' => $item->pertemuan_ke,
            'tanggal_rencana' => $item->tanggal_rencana?->format('Y-m-d'),
            'tanggal_label' => $item->tanggal_rencana?->translatedFormat('d F Y'),
            'tujuan_pembelajaran_id' => $item->tujuan_pembelajaran_id,
            'tp_label' => trim(($item->tujuanPembelajaran?->kode ? $item->tujuanPembelajaran->kode.' — ' : '').($item->tujuanPembelajaran?->tujuan ?? 'Tanpa TP')),
            'jp' => $item->jp,
            'status' => $item->status,
            'tanggal_realisasi' => $item->tanggal_realisasi?->format('Y-m-d'),
            'catatan' => $item->catatan,
            'update_url' => route('guru.kurikulum.promes.update', [$item->kurikulum_id, $item]),
            'delete_url' => route('guru.kurikulum.promes.destroy', [$item->kurikulum_id, $item]),
        ];
    }
}
