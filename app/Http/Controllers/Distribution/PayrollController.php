<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\CustomerAttendance;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    protected function buildPayrollData(Customer $customer, Carbon $start, Carbon $end): array
    {
        $attendanceByDate = CustomerAttendance::where('customer_id', $customer->id)
            ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('attendance_time')
            ->get()
            ->groupBy('attendance_date');

        $workedMinutes = 0;
        $lateMinutes = 0;
        $overtimeMinutes = 0;
        $presentDays = 0;
        $dailyRows = collect();

        foreach ($attendanceByDate as $date => $entries) {
            $in = $entries->firstWhere('type', 'in');
            $out = $entries->where('type', 'out')->last();
            $dayWorked = 0;
            $dayOvertime = 0;
            $dayLate = $in ? (int) $in->late_minutes : 0;

            if ($in) {
                $presentDays++;
                $lateMinutes += $dayLate;
            }

                if ($in && $out && $out->attendance_time->gt($in->attendance_time)) {
                    $dayWorked = $in->attendance_time->diffInMinutes($out->attendance_time);
                    $workedMinutes += $dayWorked;

                    if (! empty($customer->shift_start_time) && ! empty($customer->shift_end_time)) {
                        $dayDate = Carbon::parse($date)->toDateString();
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
                'date' => $date,
                'in_time' => $in ? $in->attendance_time : null,
                'out_time' => $out ? $out->attendance_time : null,
                'worked_hours' => round($dayWorked / 60, 2),
                'late_hours' => round($dayLate / 60, 2),
                'overtime_hours' => round($dayOvertime / 60, 2),
                'location' => $customer->attendance_location,
            ]);
        }

        $lateHours = round($lateMinutes / 60, 2);
        $overtimeHours = round($overtimeMinutes / 60, 2);
        $lateDeduction = $lateHours * (float) $customer->late_deduction_per_hour;
        $overtimePay = $overtimeHours * (float) $customer->overtime_pay_per_hour;
        $netPayable = (float) $customer->base_salary - $lateDeduction + $overtimePay;

        return [
            'customer' => $customer,
            'present_days' => $presentDays,
            'worked_hours' => round($workedMinutes / 60, 2),
            'late_hours' => $lateHours,
            'overtime_hours' => $overtimeHours,
            'base_salary' => (float) $customer->base_salary,
            'late_deduction' => round($lateDeduction, 2),
            'overtime_pay' => round($overtimePay, 2),
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
