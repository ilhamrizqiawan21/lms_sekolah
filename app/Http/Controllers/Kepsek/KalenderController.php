<?php

namespace App\Http\Controllers\Kepsek;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Services\CalendarTimelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

// Kalender Kepala Sekolah bersifat monitoring-only.
class KalenderController extends Controller
{
    public function __construct(private CalendarTimelineService $calendarTimelineService) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $year = (int) ($validated['year'] ?? date('Y'));
        $month = (int) ($validated['month'] ?? date('m'));

        $monthEvents = CalendarEvent::where('scope', 'school')
            ->with('user')
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
            ->orderBy('event_date')
            ->get();

        $eventProps = $monthEvents->map(fn (CalendarEvent $event) => $this->eventProps($event))->values();

        return Inertia::render('Kepsek/Kalender/Index', [
            'calendar' => $this->calendarTimelineService->monthGrid($year, $month, $eventProps, 'kepsek.kalender'),
            'monthEvents' => $eventProps,
            'pageTitle' => 'Kalender & Monitoring Event Sekolah',
        ]);
    }

    public function store(Request $request)
    {
        abort(403, 'Kepala sekolah hanya dapat memantau kalender.');
    }

    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        abort(403, 'Kepala sekolah hanya dapat memantau kalender.');
    }

    public function destroy(CalendarEvent $calendarEvent)
    {
        abort(403, 'Kepala sekolah hanya dapat memantau kalender.');
    }

    public function toggleDone(CalendarEvent $calendarEvent)
    {
        abort(403, 'Kepala sekolah hanya dapat memantau kalender.');
    }

    private function eventProps(CalendarEvent $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'title_short' => Str::limit($event->title, 18),
            'description' => $event->description,
            'event_date' => $event->event_date?->format('Y-m-d'),
            'event_date_label' => $event->event_date?->format('d M Y') ?? '-',
            'is_holiday' => $event->is_holiday,
            'is_done' => $event->is_done,
            'scope' => $event->scope,
            'created_by' => $event->user?->nama_lengkap ?? '-',
            'can_manage' => false,
        ];
    }
}
