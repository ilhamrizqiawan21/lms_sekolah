<?php

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\JadwalMengajar;
use App\Models\KelasMapel;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceScheduleService
{
    /**
     * Tanggal pertemuan riil satu kelas_mapel pada suatu bulan, berdasarkan
     * jadwal mengajar (JadwalMengajar) dan hari libur (CalendarEvent).
     * Sumber tunggal ini dipakai baik oleh halaman Absensi maupun ekspornya
     * agar tanggal yang ditampilkan selalu sama dengan tanggal absensi tersimpan.
     */
    public static function meetings(string $bulan, KelasMapel $kelasMapel): Collection
    {
        $schedules = JadwalMengajar::where('kelas_mapel_id', $kelasMapel->id)
            ->orderBy('hari')
            ->orderBy('pelajaran_ke')
            ->get()
            ->groupBy('hari');

        if ($schedules->isEmpty()) {
            return collect();
        }

        $start = Carbon::createFromFormat('Y-m-d', "{$bulan}-01")->startOfDay();
        $end = $start->copy()->endOfMonth();
        $holidays = CalendarEvent::where('is_holiday', true)
            ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
            ->pluck('event_date')
            ->map(fn ($date) => $date instanceof Carbon ? $date->toDateString() : Carbon::parse($date)->toDateString())
            ->flip();
        $meetings = [];
        $meetingNumber = 1;

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dayNumber = (int) $date->format('N');

            if ($dayNumber < 1 || $dayNumber > 5 || ! $schedules->has($dayNumber) || $holidays->has($date->toDateString())) {
                continue;
            }

            $slots = $schedules->get($dayNumber)
                ->pluck('pelajaran_ke')
                ->unique()
                ->sort()
                ->values();

            $meetings[] = [
                'key' => $date->toDateString(),
                'week' => (int) ceil((int) $date->format('j') / 7),
                'meeting' => $meetingNumber,
                'date' => $date->toDateString(),
                'label' => $date->format('d/m'),
                'title' => JadwalMengajar::DAYS[$dayNumber].' P'.$meetingNumber,
                'lesson_title' => 'Jam ke-'.$slots->implode('/'),
            ];
            $meetingNumber++;
        }

        return collect($meetings);
    }
}
