<?php

namespace App\Http\Controllers\Api\Customer;

use App\CustomerAttendance;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CustomerAttendanceStoreRequest;
use App\Services\DistanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class AttendanceController extends Controller
{
    public function __construct(protected DistanceService $distanceService)
    {
    }

    public function store(CustomerAttendanceStoreRequest $request): JsonResponse
    {
        $customer = auth()->user();

        if ($request->type === 'in') {
            $todayInExists = CustomerAttendance::where('customer_id', $customer->id)
                ->whereDate('attendance_date', now()->toDateString())
                ->where('type', 'in')
                ->exists();

            if ($todayInExists) {
                return response()->json(['success' => false, 'message' => 'Already checked in for today'], 422);
            }
        }

        $distance = null;
        $withinRadius = false;

        if (! is_null($customer->work_latitude) && ! is_null($customer->work_longitude)) {
            $distance = $this->distanceService->calculateMeters(
                (float) $customer->work_latitude,
                (float) $customer->work_longitude,
                (float) $request->latitude,
                (float) $request->longitude
            );
            $allowedRadius = (int) ($customer->allowed_radius_meters ?? 100);
            $withinRadius = $distance <= $allowedRadius;
        }

        if (! $withinRadius) {
            return response()->json(['success' => false, 'message' => 'You are outside allowed attendance area'], 422);
        }

        $lateMinutes = 0;
        $isLate = false;
        if ($request->type === 'in' && ! empty($customer->shift_start_time)) {
            $shiftStart = Carbon::parse(now()->toDateString().' '.$customer->shift_start_time);
            $now = now();
            if ($now->gt($shiftStart)) {
                $lateMinutes = $shiftStart->diffInMinutes($now);
                $isLate = $lateMinutes > 0;
            }
        }

        $imagePath = $this->storeImage($request);

        $attendance = CustomerAttendance::create([
            'customer_id' => $customer->id,
            'attendance_date' => now()->toDateString(),
            'type' => $request->type,
            'attendance_time' => now(),
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'distance_meters' => $distance,
            'is_within_radius' => $withinRadius,
            'is_late' => $isLate,
            'late_minutes' => $lateMinutes,
            'image_path' => $imagePath,
            'meta' => ['user_agent' => request()->userAgent()],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance submitted successfully',
            'data' => [
                'id' => $attendance->id,
                'type' => $attendance->type,
                'attendance_time' => $attendance->attendance_time->toIso8601String(),
                'is_late' => $attendance->is_late,
                'late_minutes' => $attendance->late_minutes,
                'distance_meters' => $attendance->distance_meters,
            ],
        ]);
    }

    public function details(Request $request): JsonResponse
    {
        $customer = auth()->user();
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $entries = CustomerAttendance::where('customer_id', $customer->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('attendance_time')
            ->get();

        $attendanceByDate = $entries->sortBy('attendance_time')->groupBy('attendance_date');

        $workedMinutes = 0;
        $lateMinutes = 0;
        $overtimeMinutes = 0;
        $presentDays = 0;

        foreach ($attendanceByDate as $date => $dayEntries) {
            $in = $dayEntries->firstWhere('type', 'in');
            $out = $dayEntries->where('type', 'out')->last();

            if ($in) {
                $presentDays++;
                $lateMinutes += (int) $in->late_minutes;
            }

            if ($in && $out && $out->attendance_time->gt($in->attendance_time)) {
                $worked = $in->attendance_time->diffInMinutes($out->attendance_time);
                $workedMinutes += $worked;

                if (! empty($customer->shift_start_time) && ! empty($customer->shift_end_time)) {
                    $dayDate = Carbon::parse($date)->toDateString();
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
        $baseSalary = (float) ($customer->base_salary ?? 0);
        $lateDeduction = $lateHours * (float) ($customer->late_deduction_per_hour ?? 0);
        $overtimePay = $overtimeHours * (float) ($customer->overtime_pay_per_hour ?? 0);
        $netPayable = $baseSalary - $lateDeduction + $overtimePay;

        return response()->json([
            'success' => true,
            'message' => 'Attendance details fetched successfully',
            'data' => [
                'month' => $month,
                'attendance_location' => $customer->attendance_location,
                'summary' => [
                    'present_days' => $presentDays,
                    'worked_hours' => round($workedMinutes / 60, 2),
                    'late_hours' => $lateHours,
                    'overtime_hours' => $overtimeHours,
                    'salary' => [
                        'base_salary' => $baseSalary,
                        'late_deduction' => round($lateDeduction, 2),
                        'overtime_pay' => round($overtimePay, 2),
                        'total_salary' => round($netPayable, 2),
                    ],
                ],
                'attendances' => $entries->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'date' => optional($entry->attendance_date)->toDateString(),
                        'type' => $entry->type,
                        'attendance_time' => optional($entry->attendance_time)->toIso8601String(),
                        'latitude' => $entry->latitude,
                        'longitude' => $entry->longitude,
                        'distance_meters' => $entry->distance_meters,
                        'is_within_radius' => (bool) $entry->is_within_radius,
                        'is_late' => (bool) $entry->is_late,
                        'late_minutes' => (int) $entry->late_minutes,
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
