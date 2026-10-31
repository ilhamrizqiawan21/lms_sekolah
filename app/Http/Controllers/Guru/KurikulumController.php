<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\StoreCapaianPembelajaranRequest;
use App\Http\Requests\Guru\StoreKurikulumRequest;
use App\Http\Requests\Guru\StoreTujuanPembelajaranRequest;
use App\Http\Requests\Guru\UpdateCapaianPembelajaranRequest;
use App\Http\Requests\Guru\UpdateKurikulumRequest;
use App\Http\Requests\Guru\UpdateTujuanPembelajaranRequest;
use App\Models\CapaianPembelajaran;
use App\Models\KelasMapel;
use App\Models\Kurikulum;
use App\Models\TujuanPembelajaran;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class KurikulumController extends Controller
{
    public function index()
    {
        $kurikulum = Kurikulum::query()
            ->with(['mataPelajaran', 'tahunAjaran', 'kelasMapel.kelas'])
            ->withCount(['capaianPembelajaran', 'tujuanPembelajaran'])
            ->where('guru_id', Auth::id())
            ->latest('updated_at')
            ->get()
            ->map(fn (Kurikulum $item) => $this->formatKurikulum($item))
            ->values();

        return Inertia::render('Guru/Kurikulum/Index', [
            'kurikulum' => $kurikulum,
            'kelasMapel' => $this->assignedKelasMapelQuery()
                ->get()
                ->map(fn (KelasMapel $item) => $this->formatKelasMapel($item))
                ->values(),
            'storeUrl' => route('guru.kurikulum.store'),
        ]);
    }

    public function store(StoreKurikulumRequest $request)
    {
        $validated = $request->validated();
        $kelasMapel = $this->resolveKelasMapelScope($validated);

        $kurikulum = DB::transaction(function () use ($validated, $kelasMapel) {
            $kurikulum = Kurikulum::create([
                ...collect($validated)->except('kelas_mapel_ids')->all(),
                'guru_id' => Auth::id(),
            ]);
            $kurikulum->kelasMapel()->sync($kelasMapel->modelKeys());

            return $kurikulum;
        });

        return redirect()
            ->route('guru.kurikulum.show', $kurikulum)
            ->with('success', 'Kurikulum berhasil dibuat.');
    }

    public function show(Kurikulum $kurikulum)
    {
        $this->authorize('view', $kurikulum);

        $kurikulum->load([
            'mataPelajaran',
            'tahunAjaran',
            'guru',
            'kelasMapel.kelas',
            'capaianPembelajaran',
            'tujuanPembelajaran.capaianPembelajaran',
        ]);

        return Inertia::render('Guru/Kurikulum/Show', [
            'kurikulum' => $this->formatKurikulumDetail($kurikulum),
            'updateUrl' => route('guru.kurikulum.update', $kurikulum),
            'deleteUrl' => route('guru.kurikulum.destroy', $kurikulum),
            'cpStoreUrl' => route('guru.kurikulum.cp.store', $kurikulum),
            'tpStoreUrl' => route('guru.kurikulum.tp.store', $kurikulum),
            'promesUrl' => route('guru.kurikulum.promes.index', $kurikulum),
            'documentsUrl' => route('guru.kurikulum.dokumen', ['kurikulum_id' => $kurikulum->id]),
            'moduleUrl' => route('guru.kurikulum.modul-ajar', ['kurikulum_id' => $kurikulum->id]),
        ]);
    }

    public function update(UpdateKurikulumRequest $request, Kurikulum $kurikulum)
    {
        $validated = $request->validated();
        $this->resolveKelasMapelScope($validated, $kurikulum);

        DB::transaction(function () use ($validated, $kurikulum) {
            $kurikulum->update(collect($validated)->except('kelas_mapel_ids')->all());
            $kurikulum->kelasMapel()->sync($validated['kelas_mapel_ids']);
        });

        return back()->with('success', 'Kurikulum berhasil diperbarui.');
    }

    public function destroy(Kurikulum $kurikulum)
    {
        $this->authorize('delete', $kurikulum);
        $kurikulum->delete();

        return redirect()
            ->route('guru.kurikulum.index')
            ->with('success', 'Kurikulum berhasil dihapus.');
    }

    public function storeCapaianPembelajaran(
        StoreCapaianPembelajaranRequest $request,
        Kurikulum $kurikulum,
    ) {
        $this->authorize('update', $kurikulum);
        $kurikulum->capaianPembelajaran()->create($request->validated());

        return back()->with('success', 'Capaian Pembelajaran berhasil ditambahkan.');
    }

    public function updateCapaianPembelajaran(
        UpdateCapaianPembelajaranRequest $request,
        Kurikulum $kurikulum,
        CapaianPembelajaran $capaianPembelajaran,
    ) {
        $this->assertChildBelongsToKurikulum($capaianPembelajaran, $kurikulum);
        $this->authorize('update', $kurikulum);
        $capaianPembelajaran->update($request->validated());

        return back()->with('success', 'Capaian Pembelajaran berhasil diperbarui.');
    }

    public function destroyCapaianPembelajaran(Kurikulum $kurikulum, CapaianPembelajaran $capaianPembelajaran)
    {
        $this->assertChildBelongsToKurikulum($capaianPembelajaran, $kurikulum);
        $this->authorize('update', $kurikulum);
        $capaianPembelajaran->delete();

        return back()->with('success', 'Capaian Pembelajaran berhasil dihapus.');
    }

    public function storeTujuanPembelajaran(
        StoreTujuanPembelajaranRequest $request,
        Kurikulum $kurikulum,
    ) {
        $this->authorize('update', $kurikulum);
        $kurikulum->tujuanPembelajaran()->create($request->validated());

        return back()->with('success', 'Tujuan Pembelajaran berhasil ditambahkan.');
    }

    public function updateTujuanPembelajaran(
        UpdateTujuanPembelajaranRequest $request,
        Kurikulum $kurikulum,
        TujuanPembelajaran $tujuanPembelajaran,
    ) {
        $this->assertChildBelongsToKurikulum($tujuanPembelajaran, $kurikulum);
        $this->authorize('update', $kurikulum);
        $tujuanPembelajaran->update($request->validated());

        return back()->with('success', 'Tujuan Pembelajaran berhasil diperbarui.');
    }

    public function destroyTujuanPembelajaran(Kurikulum $kurikulum, TujuanPembelajaran $tujuanPembelajaran)
    {
        $this->assertChildBelongsToKurikulum($tujuanPembelajaran, $kurikulum);
        $this->authorize('update', $kurikulum);
        $tujuanPembelajaran->delete();

        return back()->with('success', 'Tujuan Pembelajaran berhasil dihapus.');
    }

    private function assignedKelasMapelQuery()
    {
        return KelasMapel::query()
            ->with(['kelas', 'mataPelajaran', 'tahunAjaran'])
            ->where('guru_id', Auth::id())
            ->aktif()
            ->orderBy('tahun_ajaran_id')
            ->orderBy('semester')
            ->orderBy('kelas_id');
    }

    private function resolveKelasMapelScope(array $validated, ?Kurikulum $existing = null): Collection
    {
        $ids = collect($validated['kelas_mapel_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $items = $this->assignedKelasMapelQuery()->whereIn('kelas_mapel.id', $ids)->get();

        if ($items->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'kelas_mapel_ids' => 'Ada kelas-mapel yang tidak aktif atau bukan penugasan Anda.',
            ]);
        }

        $scopes = $items->map(fn (KelasMapel $item) => [
            'tahun_ajaran_id' => (int) $item->tahun_ajaran_id,
            'mapel_id' => (int) $item->mapel_id,
            'semester' => (string) $item->semester,
        ])->unique(fn (array $scope) => implode('|', $scope));

        if ($scopes->count() !== 1) {
            throw ValidationException::withMessages([
                'kelas_mapel_ids' => 'Kelas-mapel harus berada pada mata pelajaran, tahun ajaran, dan semester yang sama.',
            ]);
        }

        $scope = $scopes->first();
        if (
            (int) $validated['tahun_ajaran_id'] !== $scope['tahun_ajaran_id']
            || (int) $validated['mapel_id'] !== $scope['mapel_id']
            || (string) $validated['semester'] !== $scope['semester']
        ) {
            throw ValidationException::withMessages([
                'kelas_mapel_ids' => 'Data mata pelajaran, tahun ajaran, atau semester tidak cocok dengan kelas-mapel yang dipilih.',
            ]);
        }

        if ($existing && (int) $existing->guru_id !== (int) Auth::id()) {
            throw ValidationException::withMessages([
                'kurikulum' => 'Kurikulum ini bukan milik akun guru Anda.',
            ]);
        }

        return $items;
    }

    private function assertChildBelongsToKurikulum(object $child, Kurikulum $kurikulum): void
    {
        abort_unless((int) $child->kurikulum_id === (int) $kurikulum->id, 404);
    }

    private function formatKurikulum(Kurikulum $item): array
    {
        return [
            'id' => $item->id,
            'nama' => $item->nama,
            'jenis_kurikulum' => $item->jenis_kurikulum,
            'fase' => $item->fase,
            'semester' => $item->semester,
            'status' => $item->status,
            'deskripsi' => $item->deskripsi,
            'mata_pelajaran' => $item->mataPelajaran?->nama_mapel ?? '-',
            'tahun_ajaran' => $item->tahunAjaran?->tahun ?? '-',
            'kelas' => $item->kelasMapel->map(fn (KelasMapel $kelasMapel) => $kelasMapel->kelas?->displayName() ?? '-')->values(),
            'kelas_mapel_ids' => $item->kelasMapel->pluck('id')->values(),
            'cp_count' => (int) ($item->capaian_pembelajaran_count ?? $item->capaianPembelajaran->count()),
            'tp_count' => (int) ($item->tujuan_pembelajaran_count ?? $item->tujuanPembelajaran->count()),
            'show_url' => route('guru.kurikulum.show', $item),
            'update_url' => route('guru.kurikulum.update', $item),
            'delete_url' => route('guru.kurikulum.destroy', $item),
        ];
    }

    private function formatKurikulumDetail(Kurikulum $item): array
    {
        return [
            ...$this->formatKurikulum($item),
            'kelas_mapel_ids' => $item->kelasMapel->pluck('id')->values(),
            'kelas_mapel' => $item->kelasMapel->map(fn (KelasMapel $kelasMapel) => [
                'id' => $kelasMapel->id,
                'label' => ($kelasMapel->kelas?->displayName() ?? '-').' — '.($kelasMapel->mataPelajaran?->nama_mapel ?? '-'),
            ])->values(),
            'capaian_pembelajaran' => $item->capaianPembelajaran->map(fn (CapaianPembelajaran $cp) => [
                'id' => $cp->id,
                'kode' => $cp->kode,
                'elemen' => $cp->elemen,
                'deskripsi' => $cp->deskripsi,
                'urutan' => $cp->urutan,
                'update_url' => route('guru.kurikulum.cp.update', [$item, $cp]),
                'delete_url' => route('guru.kurikulum.cp.destroy', [$item, $cp]),
            ])->values(),
            'tujuan_pembelajaran' => $item->tujuanPembelajaran->map(fn (TujuanPembelajaran $tp) => [
                'id' => $tp->id,
                'capaian_pembelajaran_id' => $tp->capaian_pembelajaran_id,
                'capaian_kode' => $tp->capaianPembelajaran?->kode,
                'kode' => $tp->kode,
                'bab' => $tp->bab,
                'urutan' => $tp->urutan,
                'tujuan' => $tp->tujuan,
                'materi_pokok' => $tp->materi_pokok,
                'indikator_ketercapaian' => $tp->indikator_ketercapaian,
                'alokasi_jp' => $tp->alokasi_jp,
                'estimasi_pertemuan' => $tp->estimasi_pertemuan,
                'bentuk_asesmen' => $tp->bentuk_asesmen,
                'update_url' => route('guru.kurikulum.tp.update', [$item, $tp]),
                'delete_url' => route('guru.kurikulum.tp.destroy', [$item, $tp]),
            ])->values(),
        ];
    }

    private function formatKelasMapel(KelasMapel $item): array
    {
        return [
            'id' => $item->id,
            'label' => ($item->kelas?->displayName() ?? '-').' — '.($item->mataPelajaran?->nama_mapel ?? '-').' (Sem. '.$item->semester.')',
            'kelas' => $item->kelas?->displayName() ?? '-',
            'mata_pelajaran' => $item->mataPelajaran?->nama_mapel ?? '-',
            'mapel_id' => $item->mapel_id,
            'tahun_ajaran_id' => $item->tahun_ajaran_id,
            'tahun_ajaran' => $item->tahunAjaran?->tahun ?? '-',
            'semester' => (string) $item->semester,
        ];
    }
}
