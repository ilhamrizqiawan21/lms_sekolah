<?php

namespace Tests\Unit;

use App\Services\CalendarTimelineService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CalendarTimelineServiceTest extends TestCase
{
    public function test_month_grid_starts_on_monday_for_indonesian_academic_calendar(): void
    {
        $calendar = (new CalendarTimelineService)->monthGrid(2026, 3, new Collection, 'admin.kalender');

        $this->assertSame(['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'], $calendar['weekdays']);
        $this->assertSame(7, count($calendar['weeks'][0]));
        $this->assertSame([null, null, null, null, null, null, '2026-03-01'], array_column($calendar['weeks'][0], 'date'));
        $this->assertSame('2026-03-01', $calendar['weeks'][0][6]['date']);
    }
}
