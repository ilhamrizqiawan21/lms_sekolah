<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\JadwalMengajar;
use App\Models\KelasDaring;
use App\Models\KelasMapel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class JadwalController extends Controller
{
    public function index()
    {
        $siswa = Auth::user()?->siswa?->load('kelas');

        if (! $siswa) {
            return redirect()->route('login')->with('error', 'Data siswa tidak ditemukan.');
        }

        $schedules = JadwalMengajar::with(['kelasMapel.mataPelajaran', 'guru'])
            ->where('kelas_id', $siswa->kelas_id)
            ->whereHas('kelasMapel', fn ($query) => $query->aktif())
            ->orderBy('hari')
            ->orderBy('pelajaran_ke')
            ->get();

        $byDayAndSlot = $schedules->groupBy(['hari', 'pelajaran_ke']);
        $days = collect(JadwalMengajar::DAYS)
            ->map(fn (string $label, int $value) => [
                'value' => $value,
                'label' => $label,
                'is_today' => (int) now()->dayOfWeekIso === $value,
                'slots' => collect(range(1, 5))->map(function (int $slot) use ($byDayAndSlot, $value) {
                    $schedule = $byDayAndSlot->get($value)?->get($slot)?->first();

                    return [
                        'slot' => $slot,
                        'course' => $schedule ? $this->scheduleProps($schedule) : null,
                    ];
                })->values(),
            ])
            ->values();

        return Inertia::render('Siswa/Jadwal/Index', [
            'kelas' => [
                'nama' => trim(($siswa->kelas?->tingkat ? $siswa->kelas->tingkat.' ' : '').($siswa->kelas?->nama_kelas ?? '-')),
            ],
            'days' => $days,
            'summary' => [
                'total_jadwal' => $schedules->count(),
                'total_mapel' => $schedules->pluck('kelas_mapel_id')->unique()->count(),
                'hari_aktif' => $schedules->pluck('hari')->unique()->count(),
            ],
            'links' => [
                'kelas_daring' => route('siswa.kelas-daring'),
                'kalender' => route('siswa.kalender'),
            ],
        ]);
    }

    public function kelasDaring(Request $request)
    {
        $siswa = Auth::user()?->siswa?->load('kelas');

        if (! $siswa) {
            return redirect()->route('login')->with('error', 'Data siswa tidak ditemukan.');
        }

        $kelasMapel = KelasMapel::with(['mataPelajaran', 'guru'])
            ->where('kelas_id', $siswa->kelas_id)
            ->aktif()
            ->get();
        $kelasMapelIds = $kelasMapel->pluck('id');
        $selectedCourseId = $request->integer('kelas_mapel_id') ?: null;

        if ($selectedCourseId && ! $kelasMapelIds->contains($selectedCourseId)) {
            abort(403, 'Anda tidak memiliki akses ke kelas daring ini.');
        }

        $sessionsQuery = KelasDaring::with(['kelasMapel.mataPelajaran', 'guru'])
            ->whereIn('kelas_mapel_id', $kelasMapelIds);

        if ($selectedCourseId) {
            $sessionsQuery->where('kelas_mapel_id', $selectedCourseId);
        }

        $sessions = $sessionsQuery
            ->orderByRaw('case when status = ? and tanggal >= ? then 0 else 1 end', [KelasDaring::STATUS_TERJADWAL, now()->toDateString()])
            ->orderBy('tanggal')
            ->orderBy('pelajaran_ke')
            ->get();

        $absensiDaringSiswa = Absensi::where('siswa_id', $siswa->id)
            ->whereIn('kelas_daring_id', $sessions->pluck('id'))
            ->get()
            ->keyBy('kelas_daring_id');

        return Inertia::render('Siswa/KelasDaring/Index', [
            'kelas' => [
                'nama' => trim(($siswa->kelas?->tingkat ? $siswa->kelas->tingkat.' ' : '').($siswa->kelas?->nama_kelas ?? '-')),
            ],
            'courses' => $kelasMapel->map(fn (KelasMapel $item) => [
                'id' => $item->id,
                'label' => ($item->mataPelajaran?->nama_mapel ?? '-').' - '.($item->guru?->nama_lengkap ?? 'Guru belum ditetapkan'),
                'url' => route('siswa.kelas-daring', ['kelas_mapel_id' => $item->id]),
            ])->values(),
            'selectedCourseId' => $selectedCourseId,
            'sessions' => $sessions->map(function (KelasDaring $item) use ($absensiDaringSiswa) {
                $myAbsen = $absensiDaringSiswa->get($item->id);
                $isToday = $item->tanggal && $item->tanggal->toDateString() === now()->toDateString();
                $canPresensi = $item->status === KelasDaring::STATUS_TERJADWAL && $isToday && ! $myAbsen;

                return [
                    'id' => $item->id,
                    'judul' => $item->judul,
                    'deskripsi' => $item->deskripsi,
                    'mata_pelajaran' => $item->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
                    'guru' => $item->guru?->nama_lengkap ?? '-',
                    'tanggal' => $item->tanggal?->format('d M Y'),
                    'tanggal_iso' => $item->tanggal?->format('Y-m-d'),
                    'pelajaran_ke' => $item->pelajaran_ke,
                    'meeting_url' => $item->meeting_url,
                    'status' => $item->status,
                    'workspace_url' => $item->kelasMapel ? route('siswa.kelas-mapel.show', $item->kelasMapel) : null,
                    'is_upcoming' => $item->status === KelasDaring::STATUS_TERJADWAL && $item->tanggal && $item->tanggal->toDateString() >= now()->toDateString(),
                    'sudah_presensi' => $myAbsen !== null,
                    'can_presensi' => $canPresensi,
                    'presensi_url' => route('siswa.kelas-daring.presensi', $item),
                ];
            })->values(),
            'links' => [
                'all' => route('siswa.kelas-daring'),
                'jadwal' => route('siswa.jadwal-pelajaran'),
            ],
        ]);
    }

    public function presensiDaring(Request $request, KelasDaring $kelasDaring)
    {
        $siswa = Auth::user()?->siswa;

        if (! $siswa) {
            return redirect()->route('login')->with('error', 'Data siswa tidak ditemukan.');
        }

        $kelasMapel = $kelasDaring->kelasMapel;

        if (! $kelasMapel || (int) $siswa->kelas_id !== (int) $kelasMapel->kelas_id) {
            abort(403, 'Anda tidak terdaftar di kelas daring ini.');
        }

        if ($kelasDaring->status !== KelasDaring::STATUS_TERJADWAL) {
            return back()->with('error', 'Sesi kelas daring tidak sedang aktif atau sudah selesai.');
        }

        $sessionDate = $kelasDaring->tanggal?->toDateString();
        if ($sessionDate !== now()->toDateString()) {
            return back()->with('error', 'Presensi daring hanya dapat dilakukan pada tanggal pelaksanaan sesi.');
        }

        // Simpan / update status kehadiran daring
        Absensi::updateOrCreate(
            [
                'siswa_id' => $siswa->id,
                'kelas_mapel_id' => $kelasMapel->id,
                'tanggal' => $sessionDate,
            ],
            [
                'status' => 'h',
                'is_daring' => true,
                'kelas_daring_id' => $kelasDaring->id,
                'keterangan' => 'Hadir via Pembelajaran Daring',
            ]
        );

        return back()->with('success', 'Presensi daring berhasil dicatat sebagai Hadir.');
    }

    private function scheduleProps(JadwalMengajar $schedule): array
    {
        return [
            'id' => $schedule->id,
            'mata_pelajaran' => $schedule->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            'guru' => $schedule->guru?->nama_lengkap ?? '-',
            'workspace_url' => $schedule->kelasMapel ? route('siswa.kelas-mapel.show', $schedule->kelasMapel) : null,
            'kelas_daring_url' => $schedule->kelasMapel ? route('siswa.kelas-daring', ['kelas_mapel_id' => $schedule->kelasMapel->id]) : null,
        ];
    }
}
