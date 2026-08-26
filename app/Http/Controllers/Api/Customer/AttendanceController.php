<?php

namespace App\Http\Controllers\Api\Customer;

use App\CustomerAttendance;
use App\CustomerWorkLocation;
use App\DistributionHoliday;
use App\DistributionLeaveRequest;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CustomerAttendanceStoreRequest;
use App\Services\DistanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    private const LATE_APPROVAL_THRESHOLD_MINUTES = 120;

    public function __construct(protected DistanceService $distanceService)
    {
    }

    public function store(CustomerAttendanceStoreRequest $request): JsonResponse
    {
        $customer = auth()->user();
        if (! $customer->uses_attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance is not required for DSR employees.',
            ], 403);
        }

        $timezone = optional($customer->business)->time_zone ?: config('app.timezone');
        $now = Carbon::now($timezone);
        $today = $now->toDateString();
        $weekday = strtolower($now->englishDayOfWeek);
        $dailyAttendance = CustomerAttendance::where('customer_id', $customer->id)
            ->whereDate('attendance_date', $today)
            ->first();

        if ($request->type === 'in') {
            if ($dailyAttendance && $dailyAttendance->in_time) {
                return response()->json(['success' => false, 'message' => 'Already checked in for today'], 422);
            }
        }
        if ($request->type === 'out') {
            if (! $dailyAttendance || ! $dailyAttendance->in_time) {
                return response()->json(['success' => false, 'message' => 'Check-in required before check-out'], 422);
            }

            if ($dailyAttendance->out_time) {
                return response()->json(['success' => false, 'message' => 'Already checked out for today'], 422);
            }
        }

        if ($request->type === 'in') {
            $workLocation = CustomerWorkLocation::where('customer_id', $customer->id)
                ->where('weekday', $weekday)
                ->where('is_active', true)
                ->first();

            if (! $workLocation) {
                return response()->json([
                    'success' => false,
                    'message' => 'No approved work location set for today. Contact admin.',
                ], 422);
            }

            $targetLatitude = (float) $workLocation->latitude;
            $targetLongitude = (float) $workLocation->longitude;
            $allowedRadius = (int) ($workLocation->allowed_radius_meters ?? 100);
            $targetLocationName = $workLocation->location_name;
            $locationSource = 'weekly_work_location';
        } else {
            if (is_null($customer->work_latitude) || is_null($customer->work_longitude)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No check-out location set for this customer. Contact admin.',
                ], 422);
            }

            $targetLatitude = (float) $customer->work_latitude;
            $targetLongitude = (float) $customer->work_longitude;
            $allowedRadius = (int) ($customer->allowed_radius_meters ?? 100);
            $targetLocationName = $customer->attendance_location;
            $locationSource = 'customer_work_location';
        }

        $distance = $this->distanceService->calculateMeters(
            $targetLatitude,
            $targetLongitude,
            (float) $request->latitude,
            (float) $request->longitude
        );
        $withinRadius = $distance <= $allowedRadius;

        if (! $withinRadius) {
            return response()->json(['success' => false, 'message' => 'You are outside allowed attendance area'], 422);
        }

        $lateMinutes = 0;
        $isLate = false;
        if ($request->type === 'in' && ! empty($customer->shift_start_time)) {
            $shiftStart = Carbon::parse($today.' '.$customer->shift_start_time, $timezone);
            if ($now->gt($shiftStart)) {
                $lateMinutes = $shiftStart->diffInMinutes($now);
                $isLate = $lateMinutes > 0;
            }
        }

        $imagePath = $this->storeImage($request);

        if (! $dailyAttendance) {
            $dailyAttendance = new CustomerAttendance();
            $dailyAttendance->customer_id = $customer->id;
            $dailyAttendance->attendance_date = $today;
        }

        if ($request->type === 'in') {
            $dailyAttendance->in_time = $now;
            $dailyAttendance->attendance_time = $now;
            $dailyAttendance->type = 'in';
            $dailyAttendance->is_late = $isLate;
            $dailyAttendance->late_minutes = $lateMinutes;
            $dailyAttendance->late_approval_status = $lateMinutes >= self::LATE_APPROVAL_THRESHOLD_MINUTES ? 'pending' : 'approved';
            $dailyAttendance->late_approved_by = null;
            $dailyAttendance->late_approved_at = null;
            $dailyAttendance->tada_bill_amount = (float) ($customer->tada_bill_amount ?? 0);
        } else {
            $dailyAttendance->out_time = $now;
        }

        $dailyAttendance->latitude = $request->latitude;
        $dailyAttendance->longitude = $request->longitude;
        $dailyAttendance->distance_meters = $distance;
        $dailyAttendance->is_within_radius = $withinRadius;
        $dailyAttendance->image_path = $imagePath ?: $dailyAttendance->image_path;
        $meta = array_merge((array) $dailyAttendance->meta, [
            'user_agent' => request()->userAgent(),
        ]);

        if ($request->type === 'in') {
            $meta['work_location'] = [
                'day' => $weekday,
                'name' => $targetLocationName,
                'latitude' => $targetLatitude,
                'longitude' => $targetLongitude,
                'allowed_radius_meters' => $allowedRadius,
                'source' => $locationSource,
            ];
        }

        if ($request->type === 'out') {
            $meta['checkout'] = [
                'latitude' => (float) $request->latitude,
                'longitude' => (float) $request->longitude,
                'distance_meters' => (float) $distance,
                'matched_work_location' => (bool) $withinRadius,
                'checked_out_at' => $now->toIso8601String(),
                'target_location' => [
                    'name' => $targetLocationName,
                    'latitude' => $targetLatitude,
                    'longitude' => $targetLongitude,
                    'allowed_radius_meters' => $allowedRadius,
                    'source' => $locationSource,
                ],
            ];
        }

        $dailyAttendance->meta = $meta;
        $dailyAttendance->save();

        $isPendingForApproval = $dailyAttendance->late_approval_status === 'pending';

        return response()->json([
            'success' => true,
            'message' => $isPendingForApproval
                ? 'Attendance submitted and pending admin approval due to 120+ minutes late check-in'
                : 'Attendance submitted successfully',
            'data' => [
                'id' => $dailyAttendance->id,
                'in_time' => optional($dailyAttendance->in_time)->toIso8601String(),
                'out_time' => optional($dailyAttendance->out_time)->toIso8601String(),
                'is_late' => (bool) $dailyAttendance->is_late,
                'late_minutes' => (int) $dailyAttendance->late_minutes,
                'late_approval_status' => $dailyAttendance->late_approval_status,
                'distance_meters' => $dailyAttendance->distance_meters,
            ],
        ]);
    }

    public function details(Request $request): JsonResponse
    {
        $customer = auth()->user();
        if (! $customer->uses_attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance is not required for DSR employees.',
            ], 403);
        }

        $timezone = optional($customer->business)->time_zone ?: config('app.timezone');
        $month = $request->input('month', Carbon::now($timezone)->format('Y-m'));
        $start = Carbon::parse($month.'-01', $timezone)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $entries = CustomerAttendance::where('customer_id', $customer->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('attendance_date')
            ->get();
        $workLocationsByWeekday = CustomerWorkLocation::where('customer_id', $customer->id)
            ->where('is_active', true)
            ->get()
            ->keyBy('weekday');
        $countableEntries = $entries->filter(function ($entry) {
            return $entry->in_time && $entry->late_approval_status === 'approved';
        });
        $presentDateSet = $countableEntries->pluck('attendance_date')->map(fn ($d) => Carbon::parse($d)->toDateString())->toArray();

        $holidayDateSet = [];
        $holidays = DistributionHoliday::where('business_id', $customer->business_id)
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get();
        foreach ($holidays as $holiday) {
            $cursor = $holiday->start_date->copy()->startOfDay();
            $holidayEnd = $holiday->end_date->copy()->startOfDay();
            while ($cursor->lte($holidayEnd)) {
                if ($cursor->betweenIncluded($start->copy()->startOfDay(), $end->copy()->startOfDay())) {
                    $holidayDateSet[$cursor->toDateString()] = true;
                }
                $cursor->addDay();
            }
        }

        $approvedLeaveDateSet = [];
        $approvedLeaves = DistributionLeaveRequest::where('business_id', $customer->business_id)
            ->where('customer_id', $customer->id)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $end->toDateString())
            ->whereDate('to_date', '>=', $start->toDateString())
            ->get();
        foreach ($approvedLeaves as $leave) {
            $cursor = $leave->from_date->copy()->startOfDay();
            $leaveEnd = $leave->to_date->copy()->startOfDay();
            while ($cursor->lte($leaveEnd)) {
                if ($cursor->betweenIncluded($start->copy()->startOfDay(), $end->copy()->startOfDay())) {
                    $approvedLeaveDateSet[$cursor->toDateString()] = true;
                }
                $cursor->addDay();
            }
        }

        $workedMinutes = 0;
        $lateMinutes = 0;
        $overtimeMinutes = 0;
        $presentDays = 0;

        foreach ($entries as $entry) {
            if ($entry->in_time && $entry->late_approval_status === 'approved') {
                $presentDays++;
                $lateMinutes += (int) $entry->late_minutes;
            }

            if ($entry->in_time && $entry->out_time && $entry->out_time->gt($entry->in_time)) {
                $worked = $entry->in_time->diffInMinutes($entry->out_time);
                $workedMinutes += $worked;

                if (! empty($customer->shift_start_time) && ! empty($customer->shift_end_time)) {
                    $dayDate = Carbon::parse($entry->attendance_date)->toDateString();
                    $shiftStart = Carbon::parse($dayDate.' '.$customer->shift_start_time);
                    $shiftEnd = Carbon::parse($dayDate.' '.$customer->shift_end_time);
                    $scheduledMinutes = $shiftStart->diffInMinutes($shiftEnd);
                    if ($worked > $scheduledMinutes) {
                        $overtimeMinutes += ($worked - $scheduledMinutes);
                    }
                }
            }
        }

        $lateHours = round($lateMinutes / 60, 2);
        $overtimeHours = round($overtimeMinutes / 60, 2);
        $todayDate = Carbon::now($timezone)->toDateString();
        $todayAttendance = $entries->first(function ($entry) use ($todayDate) {
            return optional($entry->attendance_date)->toDateString() === $todayDate;
        });
        $todayWeekday = strtolower(Carbon::parse($todayDate)->englishDayOfWeek);
        $todayWorkLocation = $workLocationsByWeekday->get($todayWeekday);
        $todayAttendanceMeta = (array) ($todayAttendance->meta ?? []);
        $todayMetaWorkLocation = (array) ($todayAttendanceMeta['work_location'] ?? []);
        $todayWorkLocationName = $todayMetaWorkLocation['name'] ?? ($todayWorkLocation->location_name ?? null);
        $baseSalary = (float) ($customer->base_salary ?? 0);
        $totalDaysInMonth = $start->daysInMonth;
        $holidayDays = count(array_diff(array_keys($holidayDateSet), $presentDateSet));
        $approvedLeaveDays = count(array_diff(array_keys($approvedLeaveDateSet), $presentDateSet, array_keys($holidayDateSet)));
        $payableDays = min($totalDaysInMonth, $presentDays + $holidayDays + $approvedLeaveDays);
        $absentDays = max(0, $totalDaysInMonth - $payableDays);
        $perDaySalary = $totalDaysInMonth > 0 ? ($baseSalary / $totalDaysInMonth) : 0;
        $absentDeduction = $absentDays * $perDaySalary;
        $lateDeduction = $lateHours * (float) ($customer->late_deduction_per_hour ?? 0);
        $overtimePay = $overtimeHours * (float) ($customer->overtime_pay_per_hour ?? 0);
        $netPayable = $baseSalary - $absentDeduction - $lateDeduction + $overtimePay;

        return response()->json([
            'success' => true,
            'message' => 'Attendance details fetched successfully',
            'data' => [
                'month' => $month,
                'attendance_location' => $customer->attendance_location,
                'today_work_location_name' => $todayWorkLocationName,
                'today_attendance' => [
                    'date' => $todayDate,
                    'daily_location_title' => $todayWorkLocationName,
                    'in_time' => optional($todayAttendance?->in_time)->toIso8601String(),
                    'out_time' => optional($todayAttendance?->out_time)->toIso8601String(),
                    'is_late' => (bool) ($todayAttendance->is_late ?? false),
                    'late_minutes' => (int) ($todayAttendance->late_minutes ?? 0),
                    'distance_meters' => $todayAttendance->distance_meters ?? null,
                    'is_within_radius' => (bool) ($todayAttendance->is_within_radius ?? false),
                    'tada_bill_amount' => (float) ($todayAttendance->tada_bill_amount ?? 0),
                    'late_approval_status' => $todayAttendance->late_approval_status ?? 'approved',
                    'image_path' => $todayAttendance->image_path ?? null,
                ],
                'summary' => [
                    'present_days' => $presentDays,
                    'holiday_days' => $holidayDays,
                    'approved_leave_days' => $approvedLeaveDays,
                    'payable_days' => $payableDays,
                    'absent_days' => $absentDays,
                    'worked_hours' => round($workedMinutes / 60, 2),
                    'late_hours' => $lateHours,
                    'overtime_hours' => $overtimeHours,
                    'salary' => [
                        'base_salary' => $baseSalary,
                        'absent_deduction' => round($absentDeduction, 2),
                        'late_deduction' => round($lateDeduction, 2),
                        'overtime_pay' => round($overtimePay, 2),
                        'total_salary' => round($netPayable, 2),
                    ],
                ],
                'attendances' => $entries->map(function ($entry) use ($workLocationsByWeekday) {
                    $meta = (array) ($entry->meta ?? []);
                    $metaWorkLocation = (array) ($meta['work_location'] ?? []);
                    $weekday = strtolower(Carbon::parse($entry->attendance_date)->englishDayOfWeek);
                    $fallbackLocation = $workLocationsByWeekday->get($weekday);

                    return [
                        'id' => $entry->id,
                        'date' => optional($entry->attendance_date)->toDateString(),
                        'daily_location_title' => $metaWorkLocation['name'] ?? ($fallbackLocation->location_name ?? null),
                        'in_time' => optional($entry->in_time)->toIso8601String(),
                        'out_time' => optional($entry->out_time)->toIso8601String(),
                        'latitude' => $entry->latitude,
                        'longitude' => $entry->longitude,
                        'distance_meters' => $entry->distance_meters,
                        'is_within_radius' => (bool) $entry->is_within_radius,
                        'is_late' => (bool) $entry->is_late,
                        'late_minutes' => (int) $entry->late_minutes,
                        'late_approval_status' => $entry->late_approval_status ?? 'approved',
                        'tada_bill_amount' => (float) ($entry->tada_bill_amount ?? 0),
                        'image_path' => $entry->image_path,
                    ];
                })->values(),
            ],
        ]);
    }

    protected function storeImage(CustomerAttendanceStoreRequest $request): ?string
    {
        if ($request->hasFile('image')) {
            return $request->file('image')->store('attendance', 'public');
        }

        if ($request->filled('image_base64')) {
            $raw = $request->image_base64;
            if (str_contains($raw, ',')) {
                $parts = explode(',', $raw, 2);
                $raw = $parts[1];
            }
            $decoded = base64_decode($raw, true);
            if ($decoded !== false) {
                $fileName = 'attendance/'.uniqid('att_', true).'.jpg';
                Storage::disk('public')->put($fileName, $decoded);

                return $fileName;
            }
        }

        return null;
    }
}
