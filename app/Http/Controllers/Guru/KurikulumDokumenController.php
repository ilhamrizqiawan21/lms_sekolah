<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\Kurikulum;
use App\Models\KurikulumDokumen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class KurikulumDokumenController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->render($request, $request->string('jenis')->toString() ?: KurikulumDokumen::JENIS_ALOKASI_WAKTU);
    }

    public function modulAjar(Request $request): Response
    {
        return $this->render($request, KurikulumDokumen::JENIS_MODUL_AJAR);
    }

    public function alokasiWaktu(Request $request): Response
    {
        return $this->render($request, KurikulumDokumen::JENIS_ALOKASI_WAKTU);
    }

    public function prota(Request $request): Response
    {
        return $this->render($request, KurikulumDokumen::JENIS_PROTA);
    }

    public function promes(Request $request): Response
    {
        return $this->render($request, KurikulumDokumen::JENIS_PROMES);
    }

    public function kktp(Request $request): Response
    {
        return $this->render($request, KurikulumDokumen::JENIS_KKTP);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kurikulum_id' => ['required', 'integer'],
            'kelas_mapel_id' => ['nullable', 'integer'],
            'jenis' => ['required', Rule::in(KurikulumDokumen::JENIS)],
            'semester' => ['nullable', Rule::in(['1', '2'])],
            'bab_key' => ['nullable', 'string', 'max:100'],
            'judul' => ['required', 'string', 'max:180'],
            'isi' => ['required', 'array', 'max:100'],
            'status' => ['required', Rule::in(['draft', 'final'])],
        ]);

        $kurikulum = $this->ownedKurikulum((int) $validated['kurikulum_id']);
        $classMapelId = $validated['kelas_mapel_id'] ?? null;

        if ($classMapelId !== null && ! $kurikulum->kelasMapel()->whereKey($classMapelId)->exists()) {
            abort(422, 'Kelas-mapel tidak terhubung dengan kurikulum ini.');
        }

        $document = KurikulumDokumen::query()->updateOrCreate(
            [
                'kurikulum_id' => $kurikulum->id,
                'kelas_mapel_id' => $classMapelId,
                'jenis' => $validated['jenis'],
                'semester' => $validated['semester'] ?? null,
                'bab_key' => $validated['bab_key'] ?? null,
            ],
            [
                'judul' => $validated['judul'],
                'isi' => $validated['isi'],
                'status' => $validated['status'],
            ],
        );

        return back()->with('success', 'Dokumen administrasi berhasil disimpan.');
    }

    public function destroy(KurikulumDokumen $kurikulumDokumen): RedirectResponse
    {
        $this->ownedKurikulum((int) $kurikulumDokumen->kurikulum_id);
        $kurikulumDokumen->delete();

        return back()->with('success', 'Dokumen administrasi berhasil dihapus.');
    }

    private function render(Request $request, string $mode): Response
    {
        $allowedModes = KurikulumDokumen::JENIS;
        $mode = in_array($mode, $allowedModes, true) ? $mode : KurikulumDokumen::JENIS_ALOKASI_WAKTU;
        $items = Kurikulum::query()
            ->with(['mataPelajaran', 'tahunAjaran', 'kelasMapel.kelas', 'capaianPembelajaran', 'tujuanPembelajaran', 'dokumen'])
            ->where('guru_id', Auth::id())
            ->latest('updated_at')
            ->get();
        $selectedId = $request->integer('kurikulum_id');
        $selected = $items->firstWhere('id', $selectedId) ?? $items->first();

        return Inertia::render('Guru/Kurikulum/Dokumen', [
            'mode' => $mode,
            'kurikulum' => $items->map(fn (Kurikulum $item) => $this->serializeKurikulum($item))->values(),
            'selectedKurikulumId' => $selected?->id,
            'selected' => $selected ? $this->serializeKurikulum($selected, true) : null,
            'storeUrl' => route('guru.kurikulum.dokumen.store'),
            'moduleUrl' => route('guru.kurikulum.modul-ajar'),
            'documentsUrl' => route('guru.kurikulum.dokumen'),
        ]);
    }

    private function ownedKurikulum(int $id): Kurikulum
    {
        return Kurikulum::query()->where('guru_id', Auth::id())->findOrFail($id);
    }

    private function serializeKurikulum(Kurikulum $item, bool $detail = false): array
    {
        $payload = [
            'id' => $item->id,
            'nama' => $item->nama,
            'jenis_kurikulum' => $item->jenis_kurikulum,
            'fase' => $item->fase,
            'semester' => $item->semester,
            'mata_pelajaran' => $item->mataPelajaran?->nama_mapel ?? '-',
            'tahun_ajaran' => $item->tahunAjaran?->tahun ?? '-',
            'kelas' => $item->kelasMapel->map(fn ($kelasMapel) => [
                'id' => $kelasMapel->id,
                'label' => ($kelasMapel->kelas?->displayName() ?? '-').' — '.($kelasMapel->mataPelajaran?->nama_mapel ?? '-'),
            ])->values(),
            'dokumen_count' => $item->dokumen->count(),
        ];

        if (! $detail) {
            return $payload;
        }

        $payload['capaian_pembelajaran'] = $item->capaianPembelajaran->map(fn ($cp) => [
            'id' => $cp->id,
            'kode' => $cp->kode,
            'elemen' => $cp->elemen,
            'deskripsi' => $cp->deskripsi,
        ])->values();
        $payload['tujuan_pembelajaran'] = $item->tujuanPembelajaran->map(fn ($tp) => [
            'id' => $tp->id,
            'kode' => $tp->kode,
            'bab' => $tp->bab,
            'tujuan' => $tp->tujuan,
            'materi_pokok' => $tp->materi_pokok,
            'indikator_ketercapaian' => $tp->indikator_ketercapaian,
            'alokasi_jp' => (int) $tp->alokasi_jp,
            'estimasi_pertemuan' => (int) $tp->estimasi_pertemuan,
        ])->values();
        $payload['dokumen'] = $item->dokumen->map(fn (KurikulumDokumen $document) => [
            'id' => $document->id,
            'jenis' => $document->jenis,
            'semester' => $document->semester,
            'bab_key' => $document->bab_key,
            'judul' => $document->judul,
            'isi' => $document->isi ?? [],
            'status' => $document->status,
            'kelas_mapel_id' => $document->kelas_mapel_id,
            'updated_at' => $document->updated_at?->toIso8601String(),
            'delete_url' => route('guru.kurikulum.dokumen.destroy', $document),
        ])->values();

        return $payload;
    }
}
