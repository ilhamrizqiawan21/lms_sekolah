<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\StoreJurnalHarianRequest;
use App\Http\Requests\Guru\UpdateJurnalHarianRequest;
use App\Models\Absensi;
use App\Models\JurnalHarian;
use App\Models\KelasMapel;
use App\Models\RencanaPertemuan;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class JurnalHarianController extends Controller
{
    public function index(Request $request)
    {
        $kelasMapel = $this->assignedKelasMapelQuery()->get();
        $kelasMapelIds = $kelasMapel->modelKeys();
        $bulan = $request->string('bulan')->toString();
        $kelasMapelId = $request->integer('kelas_mapel_id') ?: null;

        $jurnalQuery = JurnalHarian::query()
            ->with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'rencanaPertemuan.tujuanPembelajaran'])
            ->where('guru_id', Auth::id())
            ->whereIn('kelas_mapel_id', $kelasMapelIds)
            ->when($kelasMapelId, fn ($query) => $query->where('kelas_mapel_id', $kelasMapelId))
            ->when($bulan && preg_match('/^\d{4}-\d{2}$/', $bulan), function ($query) use ($bulan) {
                $start = Carbon::createFromFormat('!Y-m', $bulan)->startOfMonth();
                $query->whereBetween('tanggal', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()]);
            })
            ->latest('tanggal')
            ->latest('id');

        $jurnal = $jurnalQuery->get();
        $attendance = $this->attendanceSummary($jurnal);
        $studentCounts = $this->studentCounts($jurnal->pluck('kelas_mapel_id')->unique()->all());

        $rencana = RencanaPertemuan::query()
            ->with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran', 'tujuanPembelajaran'])
            ->whereIn('kelas_mapel_id', $kelasMapelIds)
            ->where('status', RencanaPertemuan::STATUS_DIRENCANAKAN)
            ->whereDoesntHave('jurnalHarian')
            ->when($kelasMapelId, fn ($query) => $query->where('kelas_mapel_id', $kelasMapelId))
            ->orderBy('tanggal_rencana')
            ->orderBy('kelas_mapel_id')
            ->orderBy('pertemuan_ke')
            ->get();

        return Inertia::render('Guru/JurnalHarian/Index', [
            'jurnal' => $jurnal->map(fn (JurnalHarian $item) => $this->formatJurnal($item, $attendance, $studentCounts))->values(),
            'kelasMapel' => $kelasMapel->map(fn (KelasMapel $item) => $this->formatKelasMapel($item))->values(),
            'rencana' => $rencana->map(fn (RencanaPertemuan $item) => $this->formatRencana($item))->values(),
            'filters' => [
                'kelas_mapel_id' => $kelasMapelId ? (string) $kelasMapelId : '',
                'bulan' => $bulan,
            ],
            'storeUrl' => route('guru.jurnal-harian.store'),
            'indexUrl' => route('guru.jurnal-harian.index'),
        ]);
    }

    public function store(StoreJurnalHarianRequest $request)
    {
        $data = $request->validated();
        $kelasMapel = $this->resolveAssignedKelasMapel((int) $data['kelas_mapel_id']);
        $rencana = $this->resolveRencana($data['rencana_pertemuan_id'] ?? null, $kelasMapel);

        if ($rencana) {
            $data['tanggal'] = $rencana->tanggal_rencana->toDateString();
            $data['pertemuan_ke'] = $rencana->pertemuan_ke;
            $data['jp'] = $rencana->jp;
            $data['materi_pokok'] = filled($data['materi_pokok'] ?? null)
                ? $data['materi_pokok']
                : $rencana->tujuanPembelajaran?->materi_pokok;
            $data['tujuan_pembelajaran'] = filled($data['tujuan_pembelajaran'] ?? null)
                ? $data['tujuan_pembelajaran']
                : $rencana->tujuanPembelajaran?->tujuan;

            if ($rencana->jurnalHarian()->exists()) {
                throw ValidationException::withMessages([
                    'rencana_pertemuan_id' => 'Rencana pertemuan ini sudah memiliki jurnal harian.',
                ]);
            }
        }

        $this->ensureDateIsAvailable($kelasMapel, $data['tanggal']);

        $jurnal = DB::transaction(function () use ($data, $kelasMapel, $rencana) {
            $jurnal = JurnalHarian::create([
                ...$data,
                'guru_id' => Auth::id(),
                'tahun_ajaran_id' => $kelasMapel->tahun_ajaran_id,
                'kelas_mapel_id' => $kelasMapel->id,
                'rencana_pertemuan_id' => $rencana?->id,
                'jumlah_siswa' => $this->studentCount($kelasMapel),
            ]);

            $this->syncFinalPlan($jurnal);

            return $jurnal;
        });

        return back()->with('success', $jurnal->status === 'final'
            ? 'Jurnal harian disimpan dan rencana ditandai terlaksana.'
            : 'Jurnal harian berhasil disimpan sebagai draft.');
    }

    public function update(UpdateJurnalHarianRequest $request, JurnalHarian $jurnalHarian)
    {
        $this->authorize('update', $jurnalHarian);
        $data = $request->validated();
        $kelasMapel = $jurnalHarian->kelasMapel()->firstOrFail();

        $this->ensureDateIsAvailable($kelasMapel, $data['tanggal'], $jurnalHarian);

        DB::transaction(function () use ($data, $jurnalHarian, $kelasMapel) {
            $jurnalHarian->update([
                ...$data,
                'jumlah_siswa' => $this->studentCount($kelasMapel),
            ]);
            $this->syncFinalPlan($jurnalHarian->fresh());
        });

        return back()->with('success', 'Jurnal harian berhasil diperbarui.');
    }

    public function destroy(JurnalHarian $jurnalHarian)
    {
        $this->authorize('delete', $jurnalHarian);

        if ($jurnalHarian->status === 'final') {
            throw ValidationException::withMessages([
                'jurnal' => 'Jurnal final tidak dapat dihapus. Ubah kembali menjadi draft terlebih dahulu.',
            ]);
        }

        $jurnalHarian->delete();

        return back()->with('success', 'Draft jurnal harian berhasil dihapus.');
    }

    private function assignedKelasMapelQuery()
    {
        return KelasMapel::query()
            ->with(['kelas', 'mataPelajaran', 'tahunAjaran'])
            ->where('guru_id', Auth::id())
            ->aktif()
            ->orderBy('kelas_id')
            ->orderBy('mapel_id');
    }

    private function resolveAssignedKelasMapel(int $id): KelasMapel
    {
        return $this->assignedKelasMapelQuery()->whereKey($id)->firstOrFail();
    }

    private function resolveRencana(?int $id, KelasMapel $kelasMapel): ?RencanaPertemuan
    {
        if (! $id) {
            return null;
        }

        $rencana = RencanaPertemuan::with('tujuanPembelajaran')
            ->whereKey($id)
            ->where('kelas_mapel_id', $kelasMapel->id)
            ->first();

        if (! $rencana) {
            throw ValidationException::withMessages([
                'rencana_pertemuan_id' => 'Rencana pertemuan tidak terhubung dengan kelas-mapel yang dipilih.',
            ]);
        }

        return $rencana;
    }

    private function ensureDateIsAvailable(KelasMapel $kelasMapel, string $date, ?JurnalHarian $except = null): void
    {
        $query = JurnalHarian::query()
            ->where('kelas_mapel_id', $kelasMapel->id)
            ->whereDate('tanggal', $date);

        if ($except) {
            $query->where('id', '!=', $except->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'tanggal' => 'Jurnal untuk kelas-mapel dan tanggal tersebut sudah ada.',
            ]);
        }
    }

    private function syncFinalPlan(JurnalHarian $jurnal): void
    {
        if (! $jurnal->rencana_pertemuan_id) {
            return;
        }

        $plan = $jurnal->rencanaPertemuan()->first();
        if (! $plan) {
            return;
        }

        if ($jurnal->status === 'final') {
            $plan->update([
                'status' => RencanaPertemuan::STATUS_TERLAKSANA,
                'tanggal_realisasi' => $jurnal->tanggal,
            ]);
        } elseif ($plan->status === RencanaPertemuan::STATUS_TERLAKSANA) {
            $plan->update([
                'status' => RencanaPertemuan::STATUS_DIRENCANAKAN,
                'tanggal_realisasi' => null,
            ]);
        }
    }

    private function studentCount(KelasMapel $kelasMapel): int
    {
        return Siswa::query()
            ->where('kelas_id', $kelasMapel->kelas_id)
            ->where('status', 'aktif')
            ->count();
    }

    private function studentCounts(array $kelasMapelIds): array
    {
        if ($kelasMapelIds === []) {
            return [];
        }

        return KelasMapel::query()
            ->whereIn('id', $kelasMapelIds)
            ->get(['id', 'kelas_id'])
            ->mapWithKeys(fn (KelasMapel $item) => [$item->id => $this->studentCount($item)])
            ->all();
    }

    private function attendanceSummary($jurnal): array
    {
        if ($jurnal->isEmpty()) {
            return [];
        }

        return Absensi::query()
            ->whereIn('kelas_mapel_id', $jurnal->pluck('kelas_mapel_id')->unique())
            ->whereIn('tanggal', $jurnal->pluck('tanggal')->map(fn ($date) => $date->toDateString())->unique())
            ->get(['kelas_mapel_id', 'tanggal', 'status'])
            ->groupBy(fn (Absensi $item) => $item->kelas_mapel_id.'|'.$item->tanggal->toDateString())
            ->map(fn ($items) => [
                'hadir' => $items->where('status', 'hadir')->count(),
                'sakit' => $items->where('status', 'sakit')->count(),
                'izin' => $items->where('status', 'izin')->count(),
                'alpha' => $items->where('status', 'alpha')->count(),
            ])
            ->all();
    }

    private function formatJurnal(JurnalHarian $item, array $attendance, array $studentCounts): array
    {
        $key = $item->kelas_mapel_id.'|'.$item->tanggal->toDateString();

        return [
            'id' => $item->id,
            'kelas_mapel_id' => $item->kelas_mapel_id,
            'kelas' => $item->kelasMapel?->kelas?->displayName() ?? '-',
            'mata_pelajaran' => $item->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            'tanggal' => $item->tanggal->toDateString(),
            'tanggal_label' => $item->tanggal->translatedFormat('d F Y'),
            'pertemuan_ke' => $item->pertemuan_ke,
            'jp' => $item->jp,
            'materi_pokok' => $item->materi_pokok,
            'tujuan_pembelajaran' => $item->tujuan_pembelajaran,
            'kegiatan_pendahuluan' => $item->kegiatan_pendahuluan,
            'kegiatan_inti' => $item->kegiatan_inti,
            'kegiatan_penutup' => $item->kegiatan_penutup,
            'hasil_pengamatan' => $item->hasil_pengamatan,
            'refleksi' => $item->refleksi,
            'ket_kehadiran' => $item->ket_kehadiran,
            'jumlah_siswa' => $studentCounts[$item->kelas_mapel_id] ?? $item->jumlah_siswa,
            'attendance' => $attendance[$key] ?? ['hadir' => 0, 'sakit' => 0, 'izin' => 0, 'alpha' => 0],
            'status' => $item->status,
            'rencana_label' => $item->rencanaPertemuan?->tujuanPembelajaran?->tujuan,
            'update_url' => route('guru.jurnal-harian.update', $item),
            'delete_url' => route('guru.jurnal-harian.destroy', $item),
        ];
    }

    private function formatKelasMapel(KelasMapel $item): array
    {
        return [
            'id' => $item->id,
            'label' => ($item->kelas?->displayName() ?? '-').' — '.($item->mataPelajaran?->nama_mapel ?? '-'),
            'kelas' => $item->kelas?->displayName() ?? '-',
            'mata_pelajaran' => $item->mataPelajaran?->nama_mapel ?? '-',
        ];
    }

    private function formatRencana(RencanaPertemuan $item): array
    {
        return [
            'id' => $item->id,
            'kelas_mapel_id' => $item->kelas_mapel_id,
            'label' => ($item->kelasMapel?->kelas?->displayName() ?? '-').' — Pertemuan '.$item->pertemuan_ke.' · '.$item->tanggal_rencana?->translatedFormat('d F Y'),
            'tanggal' => $item->tanggal_rencana?->toDateString(),
            'pertemuan_ke' => $item->pertemuan_ke,
            'jp' => $item->jp,
            'materi_pokok' => $item->tujuanPembelajaran?->materi_pokok,
            'tujuan_pembelajaran' => $item->tujuanPembelajaran?->tujuan,
        ];
    }
}
