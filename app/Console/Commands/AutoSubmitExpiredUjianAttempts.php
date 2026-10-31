<?php

namespace App\Console\Commands;

use App\Models\UjianAttempt;
use App\Services\CbtScoringService;
use Illuminate\Console\Command;

class AutoSubmitExpiredUjianAttempts extends Command
{
    protected $signature = 'ujian:auto-submit-expired {--limit=500 : Jumlah attempt maksimum per eksekusi}';

    protected $description = 'Mengumpulkan otomatis attempt CBT yang sudah melewati batas waktu';

    public function handle(CbtScoringService $scoringService): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $total = 0;

        UjianAttempt::query()
            ->where('status', UjianAttempt::STATUS_SEDANG_MENGERJAKAN)
            ->whereNotNull('batas_waktu')
            ->where('batas_waktu', '<=', now())
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (UjianAttempt $attempt) use ($scoringService, &$total): void {
                $attempt->refresh();

                if ($attempt->status !== UjianAttempt::STATUS_SEDANG_MENGERJAKAN
                    || ! $attempt->batas_waktu
                    || $attempt->batas_waktu->isFuture()) {
                    return;
                }

                $scoringService->autoSubmitKarenaWaktuHabis($attempt);
                $total++;
            });

        $this->info("{$total} attempt kedaluwarsa dikumpulkan.");

        return self::SUCCESS;
    }
}
