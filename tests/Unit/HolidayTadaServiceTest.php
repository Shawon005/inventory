<?php

namespace Tests\Unit;

use App\Services\HolidayTadaService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class HolidayTadaServiceTest extends TestCase
{
    private HolidayTadaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HolidayTadaService();
    }

    public function test_it_pays_friday_tada_when_either_adjacent_day_is_present(): void
    {
        $saturday = Carbon::parse('2026-08-15');
        $asOf = Carbon::parse('2026-08-16');

        $this->assertSame('paid', $this->service->automaticStatus(true, false, $saturday, $asOf));
        $this->assertSame('paid', $this->service->automaticStatus(false, true, $saturday, $asOf));
    }

    public function test_it_marks_friday_tada_missed_after_both_adjacent_days_are_absent(): void
    {
        $status = $this->service->automaticStatus(
            false,
            false,
            Carbon::parse('2026-08-15'),
            Carbon::parse('2026-08-16')
        );

        $this->assertSame('missed', $status);
    }

    public function test_it_keeps_the_result_pending_until_saturday_is_complete(): void
    {
        $status = $this->service->automaticStatus(
            false,
            false,
            Carbon::parse('2026-08-15'),
            Carbon::parse('2026-08-15')
        );

        $this->assertSame('pending', $status);
    }

    public function test_it_returns_only_fridays_inside_the_requested_range(): void
    {
        $dates = $this->service
            ->fridaysBetween(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'))
            ->map->toDateString()
            ->all();

        $this->assertSame([
            '2026-08-07',
            '2026-08-14',
            '2026-08-21',
            '2026-08-28',
        ], $dates);
    }
}
