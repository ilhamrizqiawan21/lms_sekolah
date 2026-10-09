<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Services\CalendarTimelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

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
        $monthEvents = CalendarEvent::with('user')
            ->whereYear('event_date', $year)
            ->whereMonth('event_date', $month)
            ->where(fn ($q) => $q->where('scope', 'school')->orWhere('user_id', auth()->id()))
            ->orderBy('event_date')
            ->get();
        $bulanIndo = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        $eventProps = $monthEvents->map(fn (CalendarEvent $event) => $this->eventProps($event))->values();

        return Inertia::render('Admin/Kalender/Index', [
            'calendar' => $this->calendarTimelineService->monthGrid($year, $month, $eventProps, 'admin.kalender'),
            'monthEvents' => $eventProps,
            'storeUrl' => route('admin.kalender.store'),
            'createTitle' => 'Tambah Event',
            'scopeOptions' => [
                ['value' => 'school', 'label' => 'Sekolah'],
                ['value' => 'user', 'label' => 'Pribadi'],
            ],
            'pageTitle' => 'Kalender & Reminder',
            'monthLabel' => $bulanIndo[$month],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date',
            'description' => 'nullable|string',
            'is_holiday' => 'boolean',
            'scope' => 'required|in:user,school',
            'is_done' => 'boolean',
        ]);
        $validated['is_holiday'] = $request->boolean('is_holiday');
        $validated['is_done'] = $request->boolean('is_done');
        CalendarEvent::create($validated + ['user_id' => auth()->id()]);

        return back()->with('success', 'Event berhasil ditambahkan.');
    }

    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        abort_unless((int) $calendarEvent->user_id === (int) auth()->id(), 403);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date',
            'description' => 'nullable|string',
            'is_holiday' => 'boolean',
            'is_done' => 'boolean',
        ]);
        $validated['is_holiday'] = $request->boolean('is_holiday');
        $validated['is_done'] = $request->boolean('is_done');
        $calendarEvent->update($validated);

        return back()->with('success', 'Event berhasil diperbarui.');
    }

    public function destroy(CalendarEvent $calendarEvent)
    {
        abort_unless((int) $calendarEvent->user_id === (int) auth()->id(), 403);
        $calendarEvent->delete();

        return back()->with('success', 'Event berhasil dihapus.');
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
            'can_manage' => (int) $event->user_id === (int) auth()->id(),
            'update_url' => (int) $event->user_id === (int) auth()->id() ? route('admin.kalender.update', $event) : null,
            'delete_url' => (int) $event->user_id === (int) auth()->id() ? route('admin.kalender.destroy', $event) : null,
            'toggle_done_url' => null,
        ];
    }
}
