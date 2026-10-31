<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\JadwalMengajar;
use App\Models\KelasMapel;
use App\Models\Kurikulum;
use App\Models\RencanaPertemuan;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PromesService
{
    public function defaultPeriod(Kurikulum $kurikulum): array
    {
        $tahun = (string) $kurikulum->tahunAjaran?->tahun;
        preg_match('/(\\d{4})\\D+(\\d{4})/', $tahun, $matches);
        $tahunAwal = (int) ($matches[1] ?? now()->year);
        $tahunBerikutnya = (int) ($matches[2] ?? ($tahunAwal + 1));

        return $kurikulum->semester === '2'
            ? ['start' => "{$tahunBerikutnya}-01-01", 'end' => "{$tahunBerikutnya}-06-30"]
            : ['start' => "{$tahunAwal}-07-01", 'end' => "{$tahunAwal}-12-31"];
    }

    /**
     * Mengalokasikan total JP TP ke tanggal mengajar yang tersedia.
     *
     * @return array{created: int, unallocated_jp: int, unused_dates: int}
     */
    public function generate(
        Kurikulum $kurikulum,
        Collection $kelasMapel,
        Carbon $start,
        Carbon $end,
        int $jpPerPertemuan,
    ): array {
        $tujuanPembelajaran = $kurikulum->tujuanPembelajaran()
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();
        $totalJp = (int) $tujuanPembelajaran->sum('alokasi_jp');

        if ($totalJp < 1) {
            throw ValidationException::withMessages([
                'rencana' => 'Belum ada alokasi JP pada Tujuan Pembelajaran.',
            ]);
        }

        $holidays = CalendarEvent::query()
            ->where('scope', 'school')
            ->where('is_holiday', true)
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('event_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $created = 0;
        $unallocatedJp = 0;
        $unusedDates = 0;

        foreach ($kelasMapel as $kelasMapelItem) {
            $dates = $this->availableDates($kelasMapelItem, $start, $end, $holidays);
            $remaining = $tujuanPembelajaran->map(fn ($tp) => [
                'tp' => $tp,
                'remaining' => (int) $tp->alokasi_jp,
            ])->values()->all();
            $tpIndex = 0;
            $meetingNumber = 1;

            foreach ($dates as $date) {
                while ($tpIndex < count($remaining) && $remaining[$tpIndex]['remaining'] < 1) {
                    $tpIndex++;
                }

                if ($tpIndex >= count($remaining)) {
                    $unusedDates++;

                    continue;
                }

                $allocation = min($jpPerPertemuan, $remaining[$tpIndex]['remaining']);
                $tp = $remaining[$tpIndex]['tp'];

                RencanaPertemuan::create([
                    'kurikulum_id' => $kurikulum->id,
                    'kelas_mapel_id' => $kelasMapelItem->id,
                    'tujuan_pembelajaran_id' => $tp->id,
                    'pertemuan_ke' => $meetingNumber++,
                    'tanggal_rencana' => $date,
                    'jp' => $allocation,
                    'status' => RencanaPertemuan::STATUS_DIRENCANAKAN,
                ]);

                $remaining[$tpIndex]['remaining'] -= $allocation;
                $created++;
            }

            $unallocatedJp += (int) collect($remaining)->sum('remaining');
        }

        return [
            'created' => $created,
            'unallocated_jp' => $unallocatedJp,
            'unused_dates' => $unusedDates,
        ];
    }

    private function availableDates(KelasMapel $kelasMapel, Carbon $start, Carbon $end, Collection $holidays): Collection
    {
        $days = JadwalMengajar::query()
            ->where('kelas_mapel_id', $kelasMapel->id)
            ->pluck('hari')
            ->map(fn ($day) => (int) $day)
            ->unique()
            ->values();

        if ($days->isEmpty()) {
            throw ValidationException::withMessages([
                'rencana' => 'Kelas '.$kelasMapel->kelas?->displayName().' belum memiliki jadwal mengajar.',
            ]);
        }

        $dates = collect();
        for ($date = $start->copy()->startOfDay(); $date->lte($end); $date->addDay()) {
            $dateString = $date->toDateString();
            if ($date->isWeekday() && $days->contains((int) $date->format('N')) && ! $holidays->has($dateString)) {
                $dates->push($dateString);
            }
        }

        return $dates;
    }
}
