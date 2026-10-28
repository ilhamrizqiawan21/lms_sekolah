<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class KelasController extends Controller
{
    /**
     * Tampilkan daftar kelas.
     */
    public function index()
    {
        $kelas = Kelas::withCount([
            'siswa' => fn ($query) => $query->where('status', 'aktif'),
            'kelasMapel',
            'waliKelas',
        ])
            ->orderByRaw("CASE tingkat WHEN 'VII' THEN 1 WHEN 'VIII' THEN 2 WHEN 'IX' THEN 3 ELSE 4 END")
            ->orderBy('nama_kelas')
            ->get();

        return Inertia::render('Admin/Kelas/Index', [
            'kelas' => $kelas->map(fn (Kelas $item) => [
                'id' => $item->id,
                'tingkat' => $item->tingkat,
                'nama_kelas' => $item->nama_kelas,
                'siswa_count' => $item->siswa_count,
                'kelas_mapel_count' => $item->kelas_mapel_count,
                'wali_kelas_count' => $item->wali_kelas_count,
                'siswa_url' => route('admin.kelas-siswa.index', ['kelas_id' => $item->id]),
            ])->values(),
            'metrics' => [
                'total_kelas' => $kelas->count(),
                'total_siswa' => $kelas->sum('siswa_count'),
                'total_penugasan' => $kelas->sum('kelas_mapel_count'),
            ],
        ]);
    }

    /**
     * Simpan kelas baru.
     */
    public function store(Request $request)
    {
        $this->normalizeNamaKelas($request);

        $validated = $request->validate([
            'tingkat' => 'required|in:VII,VIII,IX',
            'nama_kelas' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9 .-]*$/',
                Rule::unique('kelas', 'nama_kelas')->where(fn ($query) => $query->where('tingkat', $request->input('tingkat'))),
            ],
        ]);

        Kelas::create($validated);

        return redirect()->route('admin.kelas.index')
            ->with('success', 'Kelas berhasil ditambahkan.');
    }

    /**
     * Update kelas.
     */
    public function update(Request $request, Kelas $kelas)
    {
        $this->normalizeNamaKelas($request);

        $validated = $request->validate([
            'tingkat' => 'required|in:VII,VIII,IX',
            'nama_kelas' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9][A-Z0-9 .-]*$/',
                Rule::unique('kelas', 'nama_kelas')
                    ->ignore($kelas->id)
                    ->where(fn ($query) => $query->where('tingkat', $request->input('tingkat'))),
            ],
        ]);

        $kelas->update($validated);

        return redirect()->route('admin.kelas.index')
            ->with('success', 'Kelas berhasil diperbarui.');
    }

    /**
     * Hapus kelas.
     */
    public function destroy(Kelas $kelas)
    {
        if ($kelas->siswa()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus kelas yang masih memiliki data siswa. Pindahkan atau arsipkan siswa terlebih dahulu.');
        }

        if ($kelas->kelasMapel()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus kelas yang masih memiliki pengaturan pengajaran. Hapus pengaturan pengajarannya terlebih dahulu.');
        }

        if ($kelas->waliKelas()->exists()) {
            return back()->with('error', 'Tidak dapat menghapus kelas yang masih memiliki wali kelas. Hapus penetapan wali kelas terlebih dahulu.');
        }

        $kelas->delete();

        return redirect()->route('admin.kelas.index')
            ->with('success', 'Kelas berhasil dihapus.');
    }

    private function normalizeNamaKelas(Request $request): void
    {
        $request->merge([
            'nama_kelas' => Str::upper(trim((string) $request->input('nama_kelas'))),
        ]);
    }
}
