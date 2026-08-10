<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\CustomerAttendance;
use App\CustomerSale;
use App\DistributionHoliday;
use App\DistributionLeaveRequest;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    protected function buildPayrollData(Customer $customer, Carbon $start, Carbon $end): array
    {
        $attendances = CustomerAttendance::where('customer_id', $customer->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('attendance_date')
            ->get();
        $presentDateSet = $attendances->filter(function ($entry) {
            return $entry->in_time && $entry->late_approval_status === 'approved';
        })->pluck('attendance_date')->map(function ($date) {
            return Carbon::parse($date)->toDateString();
        })->toArray();

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
        $totalTadaBill = 0;
        $dailyRows = collect();

        foreach ($attendances as $entry) {
            $dayWorked = 0;
            $dayOvertime = 0;
            $dayLate = $entry->in_time ? (int) $entry->late_minutes : 0;

            if ($entry->in_time && $entry->late_approval_status === 'approved') {
                $presentDays++;
                $lateMinutes += $dayLate;
                $totalTadaBill += (float) ($entry->tada_bill_amount ?? 0);
            }

                if ($entry->in_time && $entry->out_time && $entry->out_time->gt($entry->in_time)) {
                    $dayWorked = $entry->in_time->diffInMinutes($entry->out_time);
                    $workedMinutes += $dayWorked;

                    if (! empty($customer->shift_start_time) && ! empty($customer->shift_end_time)) {
                        $dayDate = Carbon::parse($entry->attendance_date)->toDateString();
                        $shiftStart = Carbon::parse($dayDate.' '.$customer->shift_start_time);
                        $shiftEnd = Carbon::parse($dayDate.' '.$customer->shift_end_time);
                        $scheduledMinutes = $shiftStart->diffInMinutes($shiftEnd);
                        if ($dayWorked > $scheduledMinutes) {
                            $dayOvertime = $dayWorked - $scheduledMinutes;
                            $overtimeMinutes += $dayOvertime;
                        }
                    }
                }

            $dailyRows->push([
                'date' => Carbon::parse($entry->attendance_date)->toDateString(),
                'in_time' => $entry->in_time,
                'out_time' => $entry->out_time,
                'worked_hours' => round($dayWorked / 60, 2),
                'late_hours' => round($dayLate / 60, 2),
                'overtime_hours' => round($dayOvertime / 60, 2),
                'location' => $customer->attendance_location,
                'tada_bill_amount' => (float) ($entry->tada_bill_amount ?? 0),
            ]);
        }

        $totalSalesAmount = (float) CustomerSale::where('customer_id', $customer->id)
            ->whereBetween('created_at', [
                $start->copy()->startOfDay()->toDateTimeString(),
                $end->copy()->endOfDay()->toDateTimeString(),
            ])
            ->sum('sale_price');

        $monthlyTargetAmount = (float) ($customer->monthly_target_amount ?? 0);
        $commissionPercent = (float) ($customer->commission_percent ?? 0);
        $excessSalesAmount = max(0, $totalSalesAmount - $monthlyTargetAmount);
        $commissionAmount = $excessSalesAmount * ($commissionPercent / 100);

        $lateHours = round($lateMinutes / 60, 2);
        $overtimeHours = round($overtimeMinutes / 60, 2);
        $totalDaysInMonth = $start->daysInMonth;
        $holidayDays = count(array_diff(array_keys($holidayDateSet), $presentDateSet));
        $approvedLeaveDays = count(array_diff(array_keys($approvedLeaveDateSet), $presentDateSet, array_keys($holidayDateSet)));
        $payableDays = min($totalDaysInMonth, $presentDays + $holidayDays + $approvedLeaveDays);
        $absentDays = max(0, $totalDaysInMonth - $payableDays);
        $perDaySalary = $totalDaysInMonth > 0 ? ((float) $customer->base_salary / $totalDaysInMonth) : 0;
        $absentDeduction = $absentDays * $perDaySalary;
        $lateDeduction = $lateHours * (float) $customer->late_deduction_per_hour;
        $overtimePay = $overtimeHours * (float) $customer->overtime_pay_per_hour;
        $grossEarnings = (float) $customer->base_salary + $overtimePay + $totalTadaBill + $commissionAmount;
        $netPayable = $grossEarnings - $absentDeduction - $lateDeduction;

        return [
            'customer' => $customer,
            'total_days' => $totalDaysInMonth,
            'present_days' => $presentDays,
            'holiday_days' => $holidayDays,
            'approved_leave_days' => $approvedLeaveDays,
            'payable_days' => $payableDays,
            'absent_days' => $absentDays,
            'worked_hours' => round($workedMinutes / 60, 2),
            'late_hours' => $lateHours,
            'overtime_hours' => $overtimeHours,
            'base_salary' => (float) $customer->base_salary,
            'absent_deduction' => round($absentDeduction, 2),
            'late_deduction' => round($lateDeduction, 2),
            'overtime_pay' => round($overtimePay, 2),
            'total_tada_bill' => round($totalTadaBill, 2),
            'monthly_sales_amount' => round($totalSalesAmount, 2),
            'monthly_target_amount' => round($monthlyTargetAmount, 2),
            'excess_sales_amount' => round($excessSalesAmount, 2),
            'commission_percent' => round($commissionPercent, 2),
            'commission_amount' => round($commissionAmount, 2),
            'gross_earnings' => round($grossEarnings, 2),
            'net_payable' => round($netPayable, 2),
            'daily_rows' => $dailyRows->sortByDesc('date')->values(),
        ];
    }

    public function index(Request $request)
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $customersQuery = Customer::where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('name');

        if ($request->filled('customer_id')) {
            $customersQuery->where('id', $request->customer_id);
        }

        $customersForFilter = Customer::where('business_id', $businessId)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $customers = $customersQuery->get();
        $rows = $customers->map(function ($customer) use ($start, $end) {
            return $this->buildPayrollData($customer, $start, $end);
        });

        return view('distribution.payroll.index', [
            'rows' => $rows,
            'selectedMonth' => $month,
            'customersForFilter' => $customersForFilter,
        ]);
    }

    public function show(Request $request, $customerId)
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');
        $customer = Customer::where('business_id', $businessId)->findOrFail($customerId);
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $payroll = $this->buildPayrollData($customer, $start, $end);

        return view('distribution.payroll.show', [
            'payroll' => $payroll,
            'selectedMonth' => $month,
        ]);
    }

    public function print(Request $request, $customerId)
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');
        $customer = Customer::where('business_id', $businessId)->findOrFail($customerId);
        $month = $request->input('month', now()->format('Y-m'));
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = (clone $start)->endOfMonth();
        $payroll = $this->buildPayrollData($customer, $start, $end);

        return view('distribution.payroll.print', [
            'payroll' => $payroll,
            'selectedMonth' => $month,
        ]);
    }
}
