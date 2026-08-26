<?php

namespace App\Services;

use App\CustomerAttendance;
use App\CustomerHolidayTadaOverride;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HolidayTadaService
{
    public const STATUS_PAID = 'paid';

    public const STATUS_MISSED = 'missed';

    public const STATUS_PENDING = 'pending';

    /**
     * Build Friday holiday TA/DA rows for the supplied employees and date range.
     */
    public function rowsForCustomers(Collection $customers, Carbon $start, Carbon $end, ?Carbon $asOf = null): Collection
    {
        if ($customers->isEmpty()) {
            return collect();
        }

        $start = $start->copy()->startOfDay();
        $end = $end->copy()->startOfDay();
        $asOf = ($asOf ?: now())->copy()->startOfDay();
        $customerIds = $customers->pluck('id')->all();

        $presentDates = CustomerAttendance::whereIn('customer_id', $customerIds)
            ->whereBetween('attendance_date', [
                $start->copy()->subDay()->toDateString(),
                $end->copy()->addDay()->toDateString(),
            ])
            ->whereNotNull('in_time')
            ->where('late_approval_status', 'approved')
            ->get(['customer_id', 'attendance_date'])
            ->mapWithKeys(function ($attendance) {
                $key = $attendance->customer_id.'|'.$attendance->attendance_date->toDateString();

                return [$key => true];
            });

        $overrides = CustomerHolidayTadaOverride::whereIn('customer_id', $customerIds)
            ->whereBetween('holiday_date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->keyBy(function ($override) {
                return $override->customer_id.'|'.$override->holiday_date->toDateString();
            });

        $fridays = $this->fridaysBetween($start, $end);
        $rows = collect();

        foreach ($customers as $customer) {
            foreach ($fridays as $friday) {
                $thursday = $friday->copy()->subDay();
                $saturday = $friday->copy()->addDay();
                $thursdayPresent = $presentDates->has($customer->id.'|'.$thursday->toDateString());
                $saturdayPresent = $presentDates->has($customer->id.'|'.$saturday->toDateString());
                $automaticStatus = $this->automaticStatus(
                    $thursdayPresent,
                    $saturdayPresent,
                    $saturday,
                    $asOf
                );
                $override = $overrides->get($customer->id.'|'.$friday->toDateString());
                $effectiveStatus = $override ? $override->status : $automaticStatus;
                $amount = $effectiveStatus === self::STATUS_PAID
                    ? (float) ($override ? $override->amount : ($customer->tada_bill_amount ?? 0))
                    : 0.0;

                $rows->push([
                    'customer' => $customer,
                    'holiday_date' => $friday->toDateString(),
                    'thursday_date' => $thursday->toDateString(),
                    'saturday_date' => $saturday->toDateString(),
                    'thursday_present' => $thursdayPresent,
                    'saturday_present' => $saturdayPresent,
                    'automatic_status' => $automaticStatus,
                    'effective_status' => $effectiveStatus,
                    'amount' => $amount,
                    'override_status' => $override ? $override->status : 'auto',
                    'override_amount' => $override ? (float) $override->amount : null,
                    'is_overridden' => (bool) $override,
                ]);
            }
        }

        return $rows->sortByDesc(function ($row) {
            return $row['holiday_date'].'|'.str_pad((string) $row['customer']->id, 10, '0', STR_PAD_LEFT);
        })->values();
    }

    public function automaticStatus(
        bool $thursdayPresent,
        bool $saturdayPresent,
        Carbon $saturday,
        Carbon $asOf
    ): string {
        if ($thursdayPresent || $saturdayPresent) {
            return self::STATUS_PAID;
        }

        return $saturday->copy()->startOfDay()->lt($asOf->copy()->startOfDay())
            ? self::STATUS_MISSED
            : self::STATUS_PENDING;
    }

    public function fridaysBetween(Carbon $start, Carbon $end): Collection
    {
        $cursor = $start->copy()->startOfDay();
        if (! $cursor->isFriday()) {
            $cursor->next(Carbon::FRIDAY);
        }

        $fridays = collect();
        while ($cursor->lte($end)) {
            $fridays->push($cursor->copy());
            $cursor->addWeek();
        }

        return $fridays;
    }
}
