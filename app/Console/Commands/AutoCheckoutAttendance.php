<?php

namespace App\Console\Commands;

use App\Customer;
use App\CustomerAttendance;
use App\CustomerWorkLocation;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AutoCheckoutAttendance extends Command
{
    private const AUTO_CHECKOUT_LATE_PENALTY_MINUTES = 60;

    protected $signature = 'attendance:auto-checkout';

    protected $description = 'Auto check-out customers at shift end time if they checked in but missed check-out';

    public function handle(): int
    {
        $today = now()->toDateString();
        $now = now();

        Customer::query()
            ->where('status', 'active')
            ->whereNotNull('shift_end_time')
            ->chunkById(100, function ($customers) use ($today, $now) {
                foreach ($customers as $customer) {
                    $attendance = CustomerAttendance::where('customer_id', $customer->id)
                        ->whereDate('attendance_date', $today)
                        ->first();

                    if (! $attendance || ! $attendance->in_time) {
                        continue;
                    }

                    if ($attendance->out_time) {
                        continue;
                    }

                    $shiftEnd = Carbon::parse($today.' '.$customer->shift_end_time);
                    if (! empty($customer->shift_start_time)) {
                        $shiftStart = Carbon::parse($today.' '.$customer->shift_start_time);
                        if ($shiftEnd->lte($shiftStart)) {
                            $shiftEnd->addDay();
                        }
                    }

                    if ($now->lt($shiftEnd)) {
                        continue;
                    }

                    $attendance->out_time = $shiftEnd;
                    $attendance->is_late = true;
                    $attendance->late_minutes = (int) $attendance->late_minutes + self::AUTO_CHECKOUT_LATE_PENALTY_MINUTES;

                    $weekday = strtolower(Carbon::parse($today)->englishDayOfWeek);
                    $workLocation = CustomerWorkLocation::where('customer_id', $customer->id)
                        ->where('weekday', $weekday)
                        ->where('is_active', true)
                        ->first();

                    $meta = (array) $attendance->meta;
                    $meta['auto_checked_out'] = true;
                    $meta['reason'] = 'Auto check-out at shift end time';
                    $meta['auto_checkout_penalty_minutes'] = self::AUTO_CHECKOUT_LATE_PENALTY_MINUTES;
                    $meta['auto_checked_out_at'] = $now->toIso8601String();
                    if ($workLocation) {
                        $meta['checkout'] = [
                            'latitude' => (float) $workLocation->latitude,
                            'longitude' => (float) $workLocation->longitude,
                            'matched_work_location' => true,
                            'checked_out_at' => $shiftEnd->toIso8601String(),
                            'auto' => true,
                        ];
                    }
                    $attendance->meta = $meta;
                    $attendance->save();
                }
            });

        $this->info('Auto check-out process completed.');

        return self::SUCCESS;
    }
}
