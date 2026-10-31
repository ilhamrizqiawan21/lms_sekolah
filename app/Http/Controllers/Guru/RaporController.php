<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Http\Requests\Guru\GenerateRaporRequest;
use App\Http\Requests\Guru\UpdateRaporRequest;
use App\Models\Absensi;
use App\Models\AbsensiWaliKelas;
use App\Models\KelasMapel;
use App\Models\NilaiAkhir;
use App\Models\Pengaturan;
use App\Models\RaporNilai;
use App\Models\RaporSiswa;
use App\Models\Siswa;
use App\Models\TahunAjaran;
use App\Models\WaliKelas;
use App\Services\NilaiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class RaporController extends Controller
{
    public function __construct(private readonly NilaiService $nilaiService) {}

    public function index(Request $request, WaliKelas $waliKelas)
    {
        $this->authorize('kelola-wali-kelas', $waliKelas);
        $semester = (string) $request->input('semester', Pengaturan::getValue('semester_aktif', '1'));
        abort_unless(in_array($semester, ['1', '2'], true), 404);
        $tahunAjaran = $waliKelas->tahunAjaran;
        $reports = RaporSiswa::with(['siswa.user', 'nilai'])
            ->where('wali_kelas_id', $waliKelas->id)
            ->where('tahun_ajaran_id', $tahunAjaran?->id)
            ->where('semester', $semester)
            ->get()
            ->keyBy('siswa_id');

        $students = $this->students($waliKelas)->map(fn (Siswa $student) => $this->formatStudent($student, $reports->get($student->id)))->values();

        return Inertia::render('Guru/WaliKelas/Rapor', [
            'waliKelas' => [
                'id' => $waliKelas->id,
                'kelas' => $waliKelas->kelas?->displayName() ?? '-',
                'tahun_ajaran' => $tahunAjaran?->tahun ?? '-',
            ],
            'semester' => $semester,
            'students' => $students,
            'generateUrl' => route('guru.wali-kelas.rapor.generate', $waliKelas),
            'indexUrl' => route('guru.wali-kelas.rapor', $waliKelas),
        ]);
    }

    public function generate(GenerateRaporRequest $request, WaliKelas $waliKelas)
    {
        $this->authorize('kelola-wali-kelas', $waliKelas);
        $semester = (string) ($request->validated()['semester'] ?? Pengaturan::getValue('semester_aktif', '1'));
        $tahunAjaran = $waliKelas->tahunAjaran;
        if (! $tahunAjaran) {
            throw ValidationException::withMessages(['rapor' => 'Tahun ajaran wali kelas belum tersedia.']);
        }

        $kelasMapel = $this->kelasMapel($waliKelas, $tahunAjaran, $semester);
        if ($kelasMapel->isEmpty()) {
            throw ValidationException::withMessages(['rapor' => 'Belum ada mata pelajaran yang terhubung dengan kelas ini.']);
        }

        DB::transaction(function () use ($waliKelas, $tahunAjaran, $semester, $kelasMapel) {
            foreach ($this->students($waliKelas) as $student) {
                $rapor = RaporSiswa::firstOrCreate([
                    'siswa_id' => $student->id,
                    'tahun_ajaran_id' => $tahunAjaran->id,
                    'semester' => $semester,
                ], [
                    'wali_kelas_id' => $waliKelas->id,
                    'status' => 'draft',
                ]);

                if ($rapor->wali_kelas_id !== $waliKelas->id) {
                    throw ValidationException::withMessages(['rapor' => 'Data rapor siswa terhubung ke wali kelas lain.']);
                }
                if ($rapor->status === 'final') {
                    continue;
                }

                $rapor->update($this->attendanceCounts($waliKelas, $student, $kelasMapel, $tahunAjaran, $semester));
                foreach ($kelasMapel as $course) {
                    $nilai = NilaiAkhir::query()
                        ->where('siswa_id', $student->id)
                        ->where('kelas_mapel_id', $course->id)
                        ->where('tahun_ajaran_id', $tahunAjaran->id)
                        ->where('semester', $semester)
                        ->first();
                    $score = $nilai?->rata_akhir;
                    $detail = $rapor->nilai()->firstOrNew(['kelas_mapel_id' => $course->id]);
                    $detail->fill([
                        'mata_pelajaran' => $course->mataPelajaran?->nama_mapel ?? '-',
                        'nilai_akhir' => $score,
                        'predikat' => $score === null ? null : $this->nilaiService->getPredikat((float) $score),
                        'deskripsi' => $detail->exists && filled($detail->deskripsi)
                            ? $detail->deskripsi
                            : $this->defaultDescription($course, $score),
                    ]);
                    $detail->save();
                }
            }
        });

        return back()->with('success', 'Draft e-Rapor berhasil dibuat atau diperbarui.');
    }

    public function update(UpdateRaporRequest $request, WaliKelas $waliKelas, RaporSiswa $raporSiswa)
    {
        $this->authorize('kelola-wali-kelas', $waliKelas);
        $this->assertBelongsToWaliKelas($raporSiswa, $waliKelas);
        if ($raporSiswa->status === 'final') {
            throw ValidationException::withMessages(['rapor' => 'Rapor final sudah dikunci dan tidak dapat diubah.']);
        }

        $data = $request->validated();
        $details = $raporSiswa->nilai()->get()->keyBy('id');
        $input = collect($data['nilai'] ?? []);
        if ($input->keys()->map(fn ($id) => (int) $id)->diff($details->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['nilai' => 'Ada detail nilai yang tidak termasuk rapor ini.']);
        }

        DB::transaction(function () use ($raporSiswa, $data, $input, $details) {
            $raporSiswa->update([
                'catatan_wali_kelas' => $data['catatan_wali_kelas'] ?? null,
                'status' => $data['status'],
            ]);
            foreach ($input as $id => $item) {
                $details->get((int) $id)?->update(['deskripsi' => $item['deskripsi'] ?? null]);
            }
        });

        return back()->with('success', $raporSiswa->status === 'final' ? 'Rapor berhasil difinalkan.' : 'Rapor berhasil disimpan.');
    }

    public function print(WaliKelas $waliKelas, RaporSiswa $raporSiswa)
    {
        $this->authorize('kelola-wali-kelas', $waliKelas);
        $this->assertBelongsToWaliKelas($raporSiswa, $waliKelas);
        $raporSiswa->load(['siswa.user', 'siswa.kelas', 'tahunAjaran', 'nilai']);

        return Inertia::render('Guru/WaliKelas/RaporCetak', [
            'waliKelas' => [
                'kelas' => $waliKelas->kelas?->displayName() ?? '-',
                'guru' => $waliKelas->guru?->nama_lengkap ?? '-',
                'back_url' => route('guru.wali-kelas.rapor', [$waliKelas, 'semester' => $raporSiswa->semester]),
            ],
            'rapor' => $this->formatReport($raporSiswa),
        ]);
    }

    private function students(WaliKelas $waliKelas)
    {
        return Siswa::with('user')->where('kelas_id', $waliKelas->kelas_id)->where('status', 'aktif')->orderBy('nis')->get();
    }

    private function kelasMapel(WaliKelas $waliKelas, TahunAjaran $tahunAjaran, string $semester)
    {
        return KelasMapel::with('mataPelajaran')
            ->where('kelas_id', $waliKelas->kelas_id)
            ->where('tahun_ajaran_id', $tahunAjaran->id)
            ->where('semester', $semester)
            ->orderBy('mapel_id')
            ->get();
    }

    private function attendanceCounts(WaliKelas $waliKelas, Siswa $student, $kelasMapel, TahunAjaran $tahunAjaran, string $semester): array
    {
        [$start, $end] = $this->semesterRange($tahunAjaran, $semester);
        $official = AbsensiWaliKelas::query()
            ->where('wali_kelas_id', $waliKelas->id)
            ->where('siswa_id', $student->id)
            ->whereBetween('tanggal', [$start, $end])
            ->get(['status']);
        $records = $official->isNotEmpty() ? $official : Absensi::query()
            ->where('siswa_id', $student->id)
            ->whereIn('kelas_mapel_id', $kelasMapel->modelKeys())
            ->whereBetween('tanggal', [$start, $end])
            ->get(['status']);

        return [
            'sakit' => $records->where('status', 'sakit')->count(),
            'izin' => $records->where('status', 'izin')->count(),
            'alpha' => $records->where('status', 'alpha')->count(),
        ];
    }

    private function semesterRange(TahunAjaran $tahunAjaran, string $semester): array
    {
        $year = (int) substr((string) $tahunAjaran->tahun, 0, 4);

        return $semester === '2'
            ? [Carbon::create($year + 1, 1, 1)->toDateString(), Carbon::create($year + 1, 6, 30)->toDateString()]
            : [Carbon::create($year, 7, 1)->toDateString(), Carbon::create($year, 12, 31)->toDateString()];
    }

    private function defaultDescription(KelasMapel $course, ?float $score): string
    {
        if ($score === null) {
            return 'Nilai belum diinput.';
        }

        return 'Menunjukkan capaian pembelajaran pada mata pelajaran '.($course->mataPelajaran?->nama_mapel ?? '-').' dengan nilai '.number_format($score, 2, ',', '.').'.';
    }

    private function formatStudent(Siswa $student, ?RaporSiswa $rapor): array
    {
        return [
            'id' => $student->id,
            'nis' => $student->nis,
            'nama' => $student->user?->nama_lengkap ?? $student->nis,
            'status' => $rapor?->status ?? 'belum',
            'sakit' => $rapor?->sakit ?? 0,
            'izin' => $rapor?->izin ?? 0,
            'alpha' => $rapor?->alpha ?? 0,
            'rapor' => $rapor ? $this->formatReport($rapor) : null,
        ];
    }

    private function formatReport(RaporSiswa $rapor): array
    {
        return [
            'id' => $rapor->id,
            'siswa' => [
                'nama' => $rapor->siswa?->user?->nama_lengkap ?? $rapor->siswa?->nis ?? '-',
                'nis' => $rapor->siswa?->nis ?? '-',
                'kelas' => $rapor->siswa?->kelas?->displayName() ?? '-',
            ],
            'tahun_ajaran' => $rapor->tahunAjaran?->tahun ?? '-',
            'semester' => $rapor->semester,
            'status' => $rapor->status,
            'catatan_wali_kelas' => $rapor->catatan_wali_kelas,
            'sakit' => $rapor->sakit,
            'izin' => $rapor->izin,
            'alpha' => $rapor->alpha,
            'nilai' => $rapor->nilai->map(fn (RaporNilai $item) => [
                'id' => $item->id,
                'mata_pelajaran' => $item->mata_pelajaran,
                'nilai_akhir' => $item->nilai_akhir,
                'predikat' => $item->predikat,
                'deskripsi' => $item->deskripsi,
            ])->values(),
            'update_url' => route('guru.wali-kelas.rapor.update', [$rapor->wali_kelas_id, $rapor]),
            'print_url' => route('guru.wali-kelas.rapor.print', [$rapor->wali_kelas_id, $rapor]),
        ];
    }

    private function assertBelongsToWaliKelas(RaporSiswa $rapor, WaliKelas $waliKelas): void
    {
        abort_unless((int) $rapor->wali_kelas_id === (int) $waliKelas->id, 404);
    }
}
