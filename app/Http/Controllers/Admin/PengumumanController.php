<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\KelasMapel;
use App\Models\Pengumuman;
use App\Services\NotifikasiService;
use App\Services\PengumumanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PengumumanController extends Controller
{
    public function __construct(
        protected PengumumanService $pengumumanService,
        protected NotifikasiService $notifikasiService,
    ) {}

    public function index()
    {
        $query = Pengumuman::with(['creator', 'kelasMapel.kelas', 'kelasMapel.mataPelajaran'])->orderByDesc('created_at');
        if (Auth::user()->isGuru()) {
            $guruKelasIds = KelasMapel::where('guru_id', Auth::id())->pluck('kelas_id')->unique()->values();
            $query->where(function ($q) use ($guruKelasIds) {
                $q->whereIn('target', ['semua', 'guru'])
                    ->orWhere('created_by', Auth::id())
                    ->orWhere(function ($q) use ($guruKelasIds) {
                        $q->where('target', 'kelas_mapel')->where(function ($q) use ($guruKelasIds) {
                            $q->whereIn('kelas_mapel_id', KelasMapel::where('guru_id', Auth::id())->select('id'));
                            foreach ($guruKelasIds as $id) {
                                $q->orWhereJsonContains('target_kelas', (string) $id);
                            }
                        });
                    });
            });
        }
        if (Auth::user()->role?->nama_role === 'kepala_sekolah') {
            $query->where(fn ($q) => $q->whereIn('target', ['semua', 'guru'])->orWhere('created_by', Auth::id()));
        }

        $pengumuman = $query->paginate(15)->withQueryString();
        $pengumuman->through(function (Pengumuman $item) {
            $prefix = $this->routePrefix();
            $item->can_edit = Auth::user()->isAdmin() || (Auth::user()->isGuru() && (int) $item->created_by === (int) Auth::id());
            $item->can_delete = $item->can_edit;
            $item->update_url = route($prefix.'.update', $item);
            $item->delete_url = route($prefix.'.destroy', $item);
            $item->show_url = route($prefix.'.show', $item);
            $item->target_kelas_ids = $item->targetKelasIds();
            $item->attachment = $item->public_file_path ? [
                'name' => $item->public_file_name ?: basename($item->public_file_path),
                'size' => $item->public_file_size,
                'url' => $item->is_public_login ? route('public-pengumuman.attachment', $item) : null,
            ] : null;

            return $item;
        });

        $kelas = Kelas::orderBy('tingkat')->orderBy('nama_kelas')->get();
        $kelasMapel = KelasMapel::with(['kelas', 'mataPelajaran'])
            ->when(Auth::user()->isGuru(), fn ($q) => $q->where('guru_id', Auth::id()))
            ->orderBy('kelas_id')->get();
        $targetKelasOptions = Auth::user()->isGuru()
            ? $kelasMapel->pluck('kelas')->filter()->unique('id')->sortBy(fn (Kelas $k) => $k->tingkat.' '.$k->nama_kelas)->values()
            : $kelas;

        $role = Auth::user()->role?->nama_role;

        return match ($role) {
            'admin', 'guru' => Inertia::render('Admin/Pengumuman/Index', compact('pengumuman', 'kelas', 'kelasMapel', 'targetKelasOptions') + [
                'routePrefix' => $this->routePrefix(),
                'storeUrl' => route($this->routePrefix().'.store'),
            ]),
            'kepala_sekolah' => Inertia::render('Kepsek/Pengumuman/Index', compact('pengumuman') + ['routePrefix' => $this->routePrefix()]),
            default => abort(403),
        };
    }

    public function show(Pengumuman $pengumuman)
    {
        $role = Auth::user()->role?->nama_role;
        $this->authorize('lihat-pengumuman', $pengumuman);
        $pengumuman->loadMissing(['creator', 'kelasMapel.kelas', 'kelasMapel.mataPelajaran']);
        $targetKelasLabels = Kelas::whereIn('id', $pengumuman->targetKelasIds())
            ->orderBy('tingkat')->orderBy('nama_kelas')->get()
            ->map(fn (Kelas $k) => trim($k->tingkat.' '.$k->nama_kelas))->values();
        $pengumuman->attachment = $pengumuman->public_file_path ? [
            'name' => $pengumuman->public_file_name ?: basename($pengumuman->public_file_path),
            'size' => $pengumuman->public_file_size,
            'url' => $pengumuman->is_public_login ? route('public-pengumuman.attachment', $pengumuman) : null,
        ] : null;

        return match ($role) {
            'admin', 'guru' => Inertia::render('Admin/Pengumuman/Show', compact('pengumuman', 'targetKelasLabels') + [
                'backUrl' => route($this->routePrefix().'.index'),
            ]),
            'kepala_sekolah' => Inertia::render('Kepsek/Pengumuman/Show', compact('pengumuman', 'targetKelasLabels') + [
                'backUrl' => route($this->routePrefix().'.index'),
            ]),
            default => abort(403),
        };
    }

    public function store(Request $request)
    {
        $role = Auth::user()->role?->nama_role;
        $allowed = match ($role) {
            'guru' => ['kelas_mapel'],
            'admin' => ['semua', 'guru', 'siswa', 'kelas_mapel'],
            default => [],
        };
        abort_unless($allowed !== [], 403);
        $v = $request->validate([
            'judul' => 'required|string|max:200', 'isi' => 'required|string',
            'target' => ['required', Rule::in($allowed)],
            'target_kelas_ids' => 'nullable|required_if:target,kelas_mapel|array',
            'target_kelas_ids.*' => 'integer|exists:kelas,id',
            'is_public_login' => 'nullable|boolean',
            'public_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,xls,xlsx,doc,docx|extensions:pdf,jpg,jpeg,png,webp,xls,xlsx,doc,docx|max:5120',
        ]);
        $v = $this->pengumumanService->prepareTarget($v, Auth::user());
        $v['is_public_login'] = $request->boolean('is_public_login');
        $v['created_by'] = Auth::id();
        $this->pengumumanService->attachPublicFile($request, $v, Auth::user());
        $pengumuman = Pengumuman::create($v);
        $this->notifikasiService->notifyPengumumanRecipients($pengumuman, Auth::id());

        return redirect()->route($this->routePrefix().'.index')->with('success', 'Pengumuman berhasil dipublikasikan.');
    }

    public function update(Request $request, Pengumuman $pengumuman)
    {
        $role = Auth::user()->role?->nama_role;
        abort_unless($role === 'admin' || ($role === 'guru' && (int) $pengumuman->created_by === (int) Auth::id()), 403);
        $allowed = $role === 'guru' ? ['kelas_mapel'] : ['semua', 'guru', 'siswa', 'kelas_mapel'];
        $v = $request->validate([
            'judul' => 'required|string|max:200', 'isi' => 'required|string',
            'target' => ['required', Rule::in($allowed)],
            'target_kelas_ids' => 'nullable|required_if:target,kelas_mapel|array',
            'target_kelas_ids.*' => 'integer|exists:kelas,id',
            'is_public_login' => 'nullable|boolean',
            'public_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp,xls,xlsx,doc,docx|extensions:pdf,jpg,jpeg,png,webp,xls,xlsx,doc,docx|max:5120',
            'remove_public_file' => 'nullable|boolean',
        ]);
        $v = $this->pengumumanService->prepareTarget($v, Auth::user());
        $v['is_public_login'] = $request->boolean('is_public_login');

        if ($request->boolean('remove_public_file') || $request->hasFile('public_file')) {
            $this->pengumumanService->deletePublicFile($pengumuman);
            $v['public_file_name'] = null;
            $v['public_file_path'] = null;
            $v['public_file_mime'] = null;
            $v['public_file_size'] = null;
        }

        $this->pengumumanService->attachPublicFile($request, $v, Auth::user());
        $pengumuman->update($v);

        return redirect()->route($this->routePrefix().'.index')->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Pengumuman $pengumuman)
    {
        $role = Auth::user()->role?->nama_role;
        abort_unless($role === 'admin' || ($role === 'guru' && (int) $pengumuman->created_by === (int) Auth::id()), 403);
        $this->pengumumanService->deletePublicFile($pengumuman);
        $pengumuman->delete();

        return redirect()->route($this->routePrefix().'.index')->with('success', 'Pengumuman berhasil dihapus.');
    }

    private function routePrefix(): string
    {
        return match (Auth::user()->role?->nama_role) {
            'guru' => 'guru.pengumuman', 'kepala_sekolah' => 'kepsek.pengumuman', default => 'admin.pengumuman',
        };
    }
}
