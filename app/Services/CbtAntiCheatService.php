<?php

namespace App\Services;

use App\Models\UjianAttempt;
use App\Models\UjianAttemptEvent;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Kebijakan anti-cheat CBT: menghitung pelanggaran dari event yang sudah dicatat (dengan cap waktu server)
 * dan mengumpulkan attempt secara otomatis bila guru menetapkan batas.
 */
class CbtAntiCheatService
{
    public function __construct(
        protected CbtScoringService $scoringService
    ) {}

    /**
     * Pelanggaran = setiap episode keluar halaman ujian, ditambah tiap keluar dari layar
     * penuh bila ujian mewajibkannya. Episode yang melewati batas lima detik ditutup
     * otomatis oleh evaluasi kebijakan.
     * heartbeat_gap, salin, dan klik kanan adalah bukti bagi guru, bukan pelanggaran.
     *
     * @param  iterable<UjianAttemptEvent>  $events  berurutan menurut waktu
     */
    public function hitungDariEvents(iterable $events, Carbon $sekarang, bool $hitungFullscreen): int
    {
        $pelanggaran = 0;
        $keluarSejak = null;

        foreach ($events as $event) {
            switch ($event->jenis) {
                case UjianAttemptEvent::JENIS_TAB_HIDDEN:
                case UjianAttemptEvent::JENIS_WINDOW_BLUR:
                    // Episode yang sudah terbuka tidak dihitung ganda.
                    $keluarSejak ??= $event->terjadi_pada;
                    break;

                case UjianAttemptEvent::JENIS_KEMBALI:
                    if ($keluarSejak !== null) {
                        $pelanggaran++;
                        $keluarSejak = null;
                    }
                    break;

                case UjianAttemptEvent::JENIS_TAB_TIMEOUT:
                    break;

                case UjianAttemptEvent::JENIS_FULLSCREEN_EXIT:
                    if ($hitungFullscreen) {
                        $pelanggaran++;
                    }
                    break;
            }
        }

        if ($keluarSejak !== null) {
            $pelanggaran++;
        }

        return $pelanggaran;
    }

    public function hitungPelanggaran(UjianAttempt $attempt, Carbon $sekarang): int
    {
        $attempt->loadMissing('ujian');

        $events = $attempt->events()
            ->orderBy('terjadi_pada')
            ->orderBy('id')
            ->get(['jenis', 'terjadi_pada']);

        return $this->hitungDariEvents($events, $sekarang, (bool) $attempt->ujian?->wajib_fullscreen);
    }

    /**
     * Hitung pelanggaran dan, bila mencapai batas guru, kumpulkan attempt.
     *
     * @return array{status: string, pelanggaran: int, maks_pelanggaran: int|null}
     */
    public function evaluasi(UjianAttempt $attempt, Carbon $sekarang): array
    {
        $attempt->loadMissing('ujian');
        $ujian = $attempt->ujian;

        if (! $ujian || ! $ujian->anticheat_aktif) {
            return ['status' => $attempt->status, 'pelanggaran' => 0, 'maks_pelanggaran' => null];
        }

        $events = $attempt->events()
            ->orderBy('terjadi_pada')
            ->orderBy('id')
            ->get(['jenis', 'terjadi_pada']);
        $pelanggaran = $this->hitungDariEvents($events, $sekarang, (bool) $ujian?->wajib_fullscreen);
        $maks = $ujian->maks_pelanggaran;
        $timeout = $this->adaEpisodeMelewatiToleransi($events, $sekarang);
        $episodeTerbuka = $this->adaEpisodeTerbuka($events);

        if (($timeout || ($maks !== null && $pelanggaran >= $maks && ! $episodeTerbuka))
            && $attempt->status === UjianAttempt::STATUS_SEDANG_MENGERJAKAN) {
            $attempt = $this->kumpulkanKarenaPelanggaran($attempt);
        }

        return [
            'status' => $attempt->status,
            'pelanggaran' => $pelanggaran,
            'maks_pelanggaran' => $maks,
        ];
    }

    /**
     * Satu episode keluar yang berlangsung lebih dari lima detik mengakhiri attempt,
     * termasuk bila tab timeout tidak sempat terkirim karena browser men-throttle tab latar.
     *
     * @param iterable<UjianAttemptEvent> $events
     */
    private function adaEpisodeMelewatiToleransi(iterable $events, Carbon $sekarang): bool
    {
        $keluarSejak = null;

        foreach ($events as $event) {
            switch ($event->jenis) {
                case UjianAttemptEvent::JENIS_TAB_HIDDEN:
                case UjianAttemptEvent::JENIS_WINDOW_BLUR:
                    $keluarSejak ??= $event->terjadi_pada;
                    break;

                case UjianAttemptEvent::JENIS_KEMBALI:
                    if ($keluarSejak !== null) {
                        if ($keluarSejak->diffInSeconds($event->terjadi_pada, true) > UjianAttemptEvent::TOLERANSI_KELUAR_DETIK) {
                            return true;
                        }
                        $keluarSejak = null;
                    }
                    break;

                case UjianAttemptEvent::JENIS_TAB_TIMEOUT:
                    return true;
            }
        }

        return $keluarSejak !== null
            && $keluarSejak->diffInSeconds($sekarang, true) > UjianAttemptEvent::TOLERANSI_KELUAR_DETIK;
    }

    /** @param iterable<UjianAttemptEvent> $events */
    private function adaEpisodeTerbuka(iterable $events): bool
    {
        $keluarSejak = null;

        foreach ($events as $event) {
            if (in_array($event->jenis, UjianAttemptEvent::JENIS_KELUAR, true)) {
                $keluarSejak ??= $event->terjadi_pada;
            } elseif ($event->jenis === UjianAttemptEvent::JENIS_KEMBALI) {
                $keluarSejak = null;
            }
        }

        return $keluarSejak !== null;
    }

    private function kumpulkanKarenaPelanggaran(UjianAttempt $attempt): UjianAttempt
    {
        return DB::transaction(function () use ($attempt) {
            // Baca ulang dengan lock: request lain yang bersamaan mungkin sudah mengumpulkan attempt ini.
            $terkini = UjianAttempt::whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if ($terkini->status !== UjianAttempt::STATUS_SEDANG_MENGERJAKAN) {
                return $terkini;
            }

            return $this->scoringService->submit($terkini, UjianAttempt::ALASAN_PELANGGARAN);
        });
    }
}
