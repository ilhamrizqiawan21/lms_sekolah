<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\KelasMapel;
use App\Models\NilaiAkhir;
use App\Models\PengumpulanTugas;
use App\Models\Siswa;
use App\Models\Tugas;
use App\Models\User;
use Illuminate\Support\Collection;

class GuruPerformanceService
{
    public function dashboard(): array
    {
        $teachers = User::with('role')
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('nama_role', 'guru'))
            ->orderBy('nama_lengkap')
            ->get();
        $teacherIds = $teachers->pluck('id');

        $kelasMapelAll = KelasMapel::with(['kelas', 'mataPelajaran'])
            ->whereIn('guru_id', $teacherIds)
            ->aktif()
            ->get();
        $kelasMapelByGuru = $kelasMapelAll->groupBy('guru_id');

        $tasksAll = Tugas::whereIn('kelas_mapel_id', $kelasMapelAll->pluck('id'))->get();
        $tasksByKelasMapel = $tasksAll->groupBy('kelas_mapel_id');

        $studentCountByClass = Siswa::whereIn('kelas_id', $kelasMapelAll->pluck('kelas_id')->unique())
            ->where('status', 'aktif')
            ->selectRaw('kelas_id, count(*) as total')
            ->groupBy('kelas_id')
            ->pluck('total', 'kelas_id');

        $submissionsAll = PengumpulanTugas::with('tugas')
            ->whereIn('tugas_id', $tasksAll->pluck('id'))
            ->get();
        $submissionsByTugas = $submissionsAll->groupBy('tugas_id');

        $rows = $teachers->map(fn (User $teacher) => $this->teacherRow(
            $teacher,
            $kelasMapelByGuru->get($teacher->id, collect()),
            $tasksByKelasMapel,
            $studentCountByClass,
            $submissionsByTugas,
        ))->values();

        return [
            'summary' => [
                'total_guru' => $rows->count(),
                'rata_skor' => $this->average($rows->pluck('score')),
                'total_tugas' => (int) $rows->sum('total_tugas'),
                'perlu_dinilai' => (int) $rows->sum('perlu_dinilai'),
            ],
            'teachers' => $rows,
            'earlyWarnings' => $this->earlyWarnings(),
        ];
    }

    private function teacherRow(
        User $teacher,
        Collection $kelasMapel,
        Collection $tasksByKelasMapel,
        Collection $studentCountByClass,
        Collection $submissionsByTugas,
    ): array {
        $kelasMapelIds = $kelasMapel->pluck('id');

        $tasks = $kelasMapelIds->flatMap(fn ($id) => $tasksByKelasMapel->get($id, collect()));
        $taskIds = $tasks->pluck('id');

        $expectedSubmissions = $tasks->sum(function (Tugas $task) use ($kelasMapel, $studentCountByClass) {
            $course = $kelasMapel->firstWhere('id', $task->kelas_mapel_id);

            return (int) ($studentCountByClass[$course?->kelas_id] ?? 0);
        });

        $submissions = $taskIds->flatMap(fn ($id) => $submissionsByTugas->get($id, collect()));
        $submitted = $submissions
            ->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)
            ->count();
        $gradable = $submissions
            ->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)
            ->count();
        $graded = $submissions
            ->filter(fn (PengumpulanTugas $item) => $item->nilai !== null)
            ->count();
        $feedback = $submissions
            ->filter(fn (PengumpulanTugas $item) => filled($item->catatan))
            ->count();
        $averageGrade = $submissions
            ->filter(fn (PengumpulanTugas $item) => $item->nilai !== null)
            ->avg(fn (PengumpulanTugas $item) => (float) $item->nilai);

        $completionScore = $expectedSubmissions > 0 ? ($submitted / $expectedSubmissions) * 100 : 0;
        $gradingScore = $gradable > 0 ? ($graded / $gradable) * 100 : 0;
        $gradeScore = $averageGrade !== null ? (float) $averageGrade : 0;
        $feedbackScore = $gradable > 0 ? ($feedback / $gradable) * 100 : 0;
        $activityScore = $kelasMapel->count() > 0
            ? min(100, ($tasks->count() / max(1, $kelasMapel->count() * 4)) * 100)
            : 0;

        $score = round(
            ($completionScore * 0.35)
            + ($gradingScore * 0.25)
            + ($gradeScore * 0.20)
            + ($feedbackScore * 0.10)
            + ($activityScore * 0.10),
            2
        );

        $pending = $submissions
            ->whereIn('status', PengumpulanTugas::STATUS_PERLU_DINILAI)
            ->filter(fn (PengumpulanTugas $item) => $item->nilai === null)
            ->count();
        $avgGradeDays = $this->averageGradeDays($submissions);

        return [
            'id' => $teacher->id,
            'nama' => $teacher->nama_lengkap,
            'username' => $teacher->username,
            'score' => $score,
            'kategori' => $this->scoreCategory($score),
            'total_kelas_mapel' => $kelasMapel->count(),
            'total_tugas' => $tasks->count(),
            'target_pengumpulan' => (int) $expectedSubmissions,
            'pengumpulan_siswa' => $submitted,
            'persen_pengumpulan' => round($completionScore, 1),
            'sudah_dinilai' => $graded,
            'perlu_dinilai' => $pending,
            'persen_dinilai' => round($gradingScore, 1),
            'rata_nilai_tugas' => $averageGrade !== null ? round((float) $averageGrade, 2) : null,
            'persen_feedback' => round($feedbackScore, 1),
            'aktivitas_tugas' => round($activityScore, 1),
            'rata_hari_penilaian' => $avgGradeDays,
            'courses' => $kelasMapel->map(fn (KelasMapel $item) => trim(($item->kelas?->nama_kelas ?? '-').' - '.($item->mataPelajaran?->nama_mapel ?? '-')))->values(),
        ];
    }

    private function earlyWarnings(): Collection
    {
        $students = Siswa::with(['user', 'kelas'])
            ->where('status', 'aktif')
            ->orderBy('nis')
            ->get();
        $studentIds = $students->pluck('id');
        $kelasIds = $students->pluck('kelas_id')->filter()->unique()->values();

        $coursesByKelas = KelasMapel::aktif()
            ->whereIn('kelas_id', $kelasIds)
            ->get()
            ->groupBy('kelas_id')
            ->map(fn ($courses) => $courses->pluck('id'));
        $allCourseIds = $coursesByKelas->flatten()->unique()->values();

        $totalTasksByCourse = Tugas::whereIn('kelas_mapel_id', $allCourseIds)
            ->selectRaw('kelas_mapel_id, count(*) as total')
            ->groupBy('kelas_mapel_id')
            ->pluck('total', 'kelas_mapel_id');

        $taskKelasMapelById = Tugas::whereIn('kelas_mapel_id', $allCourseIds)
            ->pluck('kelas_mapel_id', 'id');

        $submissionsByStudent = PengumpulanTugas::whereIn('siswa_id', $studentIds)
            ->whereIn('status', PengumpulanTugas::STATUS_SUBMITTED)
            ->get(['siswa_id', 'tugas_id'])
            ->groupBy('siswa_id');

        $averageGradeByStudent = NilaiAkhir::whereIn('siswa_id', $studentIds)
            ->whereIn('kelas_mapel_id', $allCourseIds)
            ->selectRaw('siswa_id, AVG('.NilaiAkhir::rataAkhirExpression().') as rata')
            ->groupBy('siswa_id')
            ->pluck('rata', 'siswa_id');

        $alphaCountByStudent = Absensi::whereIn('siswa_id', $studentIds)
            ->whereIn('kelas_mapel_id', $allCourseIds)
            ->where('status', 'alpha')
            ->where('tanggal', '>=', now()->subDays(60)->toDateString())
            ->selectRaw('siswa_id, count(*) as total')
            ->groupBy('siswa_id')
            ->pluck('total', 'siswa_id');

        return $students->map(function (Siswa $student) use (
            $coursesByKelas,
            $totalTasksByCourse,
            $taskKelasMapelById,
            $submissionsByStudent,
            $averageGradeByStudent,
            $alphaCountByStudent,
        ) {
            $courseIds = $coursesByKelas->get($student->kelas_id, collect());
            $totalTasks = $courseIds->sum(fn ($courseId) => (int) ($totalTasksByCourse[$courseId] ?? 0));
            $submitted = $submissionsByStudent->get($student->id, collect())
                ->filter(fn ($submission) => $courseIds->contains($taskKelasMapelById[$submission->tugas_id] ?? null))
                ->count();
            $missingTasks = max(0, $totalTasks - $submitted);
            $averageGrade = $averageGradeByStudent[$student->id] ?? null;
            $alphaCount = (int) ($alphaCountByStudent[$student->id] ?? 0);

            $reasons = [];
            if ($missingTasks >= 3) {
                $reasons[] = "{$missingTasks} tugas belum dikumpulkan";
            }
            if ($averageGrade !== null && (float) $averageGrade < 75) {
                $reasons[] = 'rata-rata nilai di bawah 75';
            }
            if ($alphaCount >= 3) {
                $reasons[] = "{$alphaCount} alpha dalam 60 hari";
            }

            if ($reasons === []) {
                return null;
            }

            return [
                'id' => $student->id,
                'nama' => $student->user?->nama_lengkap ?? $student->nis,
                'nis' => $student->nis,
                'kelas' => trim(($student->kelas?->tingkat ? $student->kelas->tingkat.' ' : '').($student->kelas?->nama_kelas ?? '-')),
                'reasons' => implode(', ', $reasons),
                'missing_tasks' => $missingTasks,
                'alpha_count' => $alphaCount,
                'average_grade' => $averageGrade !== null ? round((float) $averageGrade, 2) : null,
            ];
        })->filter()->sortByDesc(fn ($item) => $item['missing_tasks'] + $item['alpha_count'])->take(10)->values();
    }

    private function average(Collection $values): float
    {
        $filtered = $values->filter(fn ($value) => $value !== null);

        return $filtered->isNotEmpty() ? round((float) $filtered->avg(), 2) : 0.0;
    }

    private function averageGradeDays(Collection $submissions): ?float
    {
        $days = $submissions
            ->filter(fn (PengumpulanTugas $item) => $item->tanggal_kumpul && $item->graded_at)
            ->map(fn (PengumpulanTugas $item) => max(0, $item->tanggal_kumpul->diffInDays($item->graded_at, false)));

        return $days->isNotEmpty() ? round((float) $days->avg(), 1) : null;
    }

    private function scoreCategory(float $score): string
    {
        return match (true) {
            $score >= 85 => 'Sangat baik',
            $score >= 75 => 'Baik',
            $score >= 60 => 'Perlu dipantau',
            default => 'Perlu pendampingan',
        };
    }
}
