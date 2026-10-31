<?php

namespace App\Http\Controllers\Guru;

use App\Http\Controllers\Controller;
use App\Models\SoalBank;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianSoal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalisisButirSoalController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $exams = $this->ownedExams();
        $selected = $request->integer('ujian_id')
            ? $exams->firstWhere('id', $request->integer('ujian_id'))
            : $exams->first();
        if ($request->filled('ujian_id')) {
            abort_unless($selected, 404);
        }

        return Inertia::render('Guru/AnalisisButirSoal/Index', [
            'ujian' => $exams->map(fn (Ujian $exam) => $this->serializeExam($exam))->values(),
            'selectedUjianId' => $selected?->id,
            'analisis' => $selected ? $this->report($selected) : null,
            'exportUrl' => $selected ? route('guru.analisis-butir-soal.export', ['ujian_id' => $selected->id]) : null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $exams = $this->ownedExams();
        $exam = $exams->firstWhere('id', $request->integer('ujian_id'));
        abort_unless($exam, 404);
        $report = $this->report($exam);

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Analisis Butir Soal']);
            fputcsv($handle, ['Ujian', $report['ujian']['judul']]);
            fputcsv($handle, ['Attempt dinilai', $report['summary']['attempts']]);
            fputcsv($handle, []);
            fputcsv($handle, ['No', 'Pertanyaan', 'Tipe', 'Poin Maksimal', 'Terjawab', 'Benar', 'Tingkat Kesukaran (%)', 'Rata-rata Poin (%)']);
            foreach ($report['items'] as $item) {
                fputcsv($handle, [
                    $item['urutan'], $item['pertanyaan'], $item['tipe'], $item['poin_maksimal'],
                    $item['terjawab'], $item['benar'] ?? 'N/A', $item['tingkat_kesukaran'] ?? 'N/A',
                    $item['rata_rata_poin'] === null ? 'N/A' : $item['rata_rata_poin'].' ('.$item['rata_rata_persen'].'%)',
                ]);
            }
            fclose($handle);
        }, 'analisis-butir-soal-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function ownedExams()
    {
        return Ujian::query()
            ->with(['kelasMapel.kelas', 'kelasMapel.mataPelajaran'])
            ->whereHas('kelasMapel', fn ($query) => $query->where('guru_id', Auth::id()))
            ->withCount(['attempts as attempts_selesai' => fn ($query) => $query->whereIn('status', UjianAttempt::STATUS_TERKUNCI)])
            ->orderByDesc('waktu_mulai')
            ->orderByDesc('id')
            ->get();
    }

    private function report(Ujian $exam): array
    {
        $exam->loadMissing(['ujianSoal.soalBank', 'attempts.jawaban']);
        $attempts = $exam->attempts->whereIn('status', UjianAttempt::STATUS_TERKUNCI)->values();
        $items = $exam->ujianSoal->sortBy('urutan')->values()->map(function (UjianSoal $item) use ($attempts): array {
            $question = $item->soalBank;
            $answers = $attempts->flatMap(fn (UjianAttempt $attempt) => $attempt->jawaban->where('ujian_soal_id', $item->id));
            $answered = $answers->filter(fn ($answer) => $this->answered($answer));
            $objective = $question && $question->tipe_soal !== SoalBank::TYPE_URAIAN;
            $gradedObjective = $answered->filter(fn ($answer) => $answer->is_benar !== null);
            $correct = $gradedObjective->where('is_benar', true)->count();
            $averagePoints = $attempts->count() > 0 ? round((float) $answers->sum(fn ($answer) => (float) ($answer->poin_didapat ?? 0)) / $attempts->count(), 2) : null;

            return [
                'id' => $item->id,
                'urutan' => (int) $item->urutan,
                'pertanyaan' => (string) ($question?->pertanyaan ?? '-'),
                'tipe' => $this->typeLabel($question?->tipe_soal),
                'poin_maksimal' => (float) $item->poin,
                'terjawab' => $answered->count(),
                'benar' => $objective ? $correct : null,
                'tingkat_kesukaran' => $objective && $gradedObjective->isNotEmpty() ? round($correct / $gradedObjective->count() * 100, 1) : null,
                'rata_rata_poin' => $averagePoints,
                'rata_rata_persen' => $averagePoints !== null && (float) $item->poin > 0 ? round($averagePoints / (float) $item->poin * 100, 1) : null,
            ];
        })->all();

        $scores = $attempts->pluck('skor_total')->filter(fn ($score) => $score !== null)->map(fn ($score) => (float) $score);

        return [
            'ujian' => [
                'id' => $exam->id,
                'judul' => $exam->judul,
                'kelas' => $exam->kelasMapel?->kelas?->displayName() ?? '-',
                'mata_pelajaran' => $exam->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            ],
            'summary' => [
                'attempts' => $attempts->count(),
                'rata_rata' => $scores->isNotEmpty() ? round($scores->avg(), 2) : null,
                'soal' => count($items),
                'objektif' => collect($items)->whereNotNull('tingkat_kesukaran')->count(),
            ],
            'items' => $items,
        ];
    }

    private function answered($answer): bool
    {
        return $answer->dijawab_pada !== null
            || filled($answer->soal_bank_opsi_id)
            || filled($answer->jawaban_teks)
            || filled($answer->jawaban_pasangan);
    }

    private function typeLabel(?string $type): string
    {
        return match ($type) {
            SoalBank::TYPE_PILIHAN_GANDA => 'Pilihan Ganda',
            SoalBank::TYPE_MENJODOHKAN => 'Menjodohkan',
            SoalBank::TYPE_BENAR_SALAH => 'Benar / Salah',
            SoalBank::TYPE_URAIAN => 'Uraian',
            default => 'Tidak diketahui',
        };
    }

    private function serializeExam(Ujian $exam): array
    {
        return [
            'id' => $exam->id,
            'judul' => $exam->judul,
            'kelas' => $exam->kelasMapel?->kelas?->displayName() ?? '-',
            'mata_pelajaran' => $exam->kelasMapel?->mataPelajaran?->nama_mapel ?? '-',
            'attempts_selesai' => (int) $exam->attempts_selesai,
            'waktu_mulai' => $exam->waktu_mulai?->format('d/m/Y H:i'),
        ];
    }
}
