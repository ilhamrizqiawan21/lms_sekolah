<?php

namespace App\Http\Controllers\Siswa;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Services\CalendarTimelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class KalenderController extends Controller
{
    public function index(Request $request, CalendarTimelineService $calendarTimeline)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|between:2000,2100',
            'month' => 'nullable|integer|between:1,12',
        ]);

        $year = $validated['year'] ?? (int) date('Y');
        $month = $validated['month'] ?? (int) date('m');

        $monthEvents = CalendarEvent::whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
            ->where(fn ($q) => $q->where('scope', 'school')->orWhere('user_id', auth()->id()))
            ->orderBy('event_date')->get();
        $eventProps = $monthEvents->map(fn (CalendarEvent $event) => [
            'id' => $event->id,
            'title' => $event->title,
            'title_short' => Str::limit($event->title, 14),
            'description' => $event->description ?: 'Tidak ada deskripsi',
            'event_date' => $event->event_date?->format('Y-m-d'),
            'event_date_label' => $event->event_date?->format('d M Y') ?? '-',
            'is_holiday' => $event->is_holiday,
            'is_done' => $event->is_done,
        ])->values();
        $calendar = $calendarTimeline->monthGrid($year, $month, $eventProps, 'siswa.kalender');

        return Inertia::render('Siswa/Kalender/Index', [
            'calendar' => $calendar,
            'monthEvents' => $eventProps,
            'pageTitle' => 'Kalender & Reminder',
        ]);
    }
}
