<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\CustomerAttendance;
use App\CustomerHolidayTadaOverride;
use App\CustomerSale;
use App\CustomerShopVisit;
use App\Http\Controllers\Controller;
use App\Services\HolidayTadaService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request, HolidayTadaService $holidayTadaService)
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');
        $customers = Customer::where('business_id', $businessId)
            ->where('employee_type', Customer::TYPE_SR)
            ->orderBy('name')
            ->get(['id', 'name', 'tada_bill_amount']);

        $query = CustomerAttendance::with('customer')
            ->whereHas('customer', function ($q) use ($businessId) {
                $q->where('business_id', $businessId)
                    ->where('employee_type', Customer::TYPE_SR);
            })
            ->orderByDesc('attendance_date')
            ->orderByDesc('in_time');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->date);
        }

        $attendances = $query->paginate(25);
        $allAttendanceKeys = $attendances->getCollection()->map(function ($attendance) {
            return [
                'customer_id' => $attendance->customer_id,
                'date' => optional($attendance->attendance_date)->toDateString(),
            ];
        })->filter(function ($key) {
            return ! empty($key['customer_id']) && ! empty($key['date']);
        })->unique(function ($key) {
            return $key['customer_id'].'|'.$key['date'];
        })->values();
        $salesAttendanceKeys = $allAttendanceKeys->filter(function ($key) use ($attendances) {
            return $attendances->getCollection()->contains(function ($attendance) use ($key) {
                return (int) $attendance->customer_id === (int) $key['customer_id']
                    && optional($attendance->attendance_date)->toDateString() === $key['date']
                    && ! empty($attendance->out_time);
            });
        })->values();

        $salesByAttendance = collect();
        if ($salesAttendanceKeys->isNotEmpty()) {
            $sales = CustomerSale::with('product')
                ->where(function ($salesQuery) use ($salesAttendanceKeys) {
                    foreach ($salesAttendanceKeys as $key) {
                        $salesQuery->orWhere(function ($pairQuery) use ($key) {
                            $pairQuery->where('customer_id', $key['customer_id'])
                                ->whereDate('created_at', $key['date']);
                        });
                    }
                })
                ->orderBy('created_at')
                ->get();

            $salesByAttendance = $sales->groupBy(function ($sale) {
                return $sale->customer_id.'|'.$sale->created_at->toDateString();
            });
        }

        $visitsByAttendance = collect();
        if ($allAttendanceKeys->isNotEmpty()) {
            $visits = CustomerShopVisit::with('shopOwner')
                ->where(function ($visitsQuery) use ($allAttendanceKeys) {
                    foreach ($allAttendanceKeys as $key) {
                        $visitsQuery->orWhere(function ($pairQuery) use ($key) {
                            $pairQuery->where('customer_id', $key['customer_id'])
                                ->whereDate('visited_at', $key['date']);
                        });
                    }
                })
                ->orderBy('visited_at')
                ->get();

            $visitsByAttendance = $visits->groupBy(function ($visit) {
                return $visit->customer_id.'|'.$visit->visited_at->toDateString();
            });
        }

        $defaultHolidayTadaMonth = $request->filled('date')
            ? Carbon::parse($request->date)->format('Y-m')
            : now()->format('Y-m');
        $holidayTadaMonth = $request->input('holiday_tada_month', $defaultHolidayTadaMonth);
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $holidayTadaMonth)) {
            $holidayTadaMonth = $defaultHolidayTadaMonth;
        }

        $holidayTadaStart = Carbon::parse($holidayTadaMonth.'-01')->startOfMonth();
        $holidayTadaEnd = $holidayTadaStart->copy()->endOfMonth();
        $holidayTadaCustomers = $request->filled('customer_id')
            ? $customers->where('id', (int) $request->customer_id)->values()
            : $customers;
        $holidayTadaRows = $holidayTadaService->rowsForCustomers(
            $holidayTadaCustomers,
            $holidayTadaStart,
            $holidayTadaEnd
        )->filter(function ($row) {
            return in_array($row['effective_status'], [
                HolidayTadaService::STATUS_PENDING,
                HolidayTadaService::STATUS_MISSED,
            ], true)
                && $row['holiday_date'] <= now()->toDateString();
        })->values();

        return view('distribution.attendance.index', compact(
            'attendances',
            'customers',
            'salesByAttendance',
            'visitsByAttendance',
            'holidayTadaRows',
            'holidayTadaMonth'
        ));
    }

    public function updateTadaBill(Request $request, $attendanceId)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'tada_bill_amount' => 'required|numeric|min:0',
        ]);

        $businessId = request()->session()->get('user.business_id');
        $attendance = CustomerAttendance::whereHas('customer', function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
                ->where('employee_type', Customer::TYPE_SR);
        })->findOrFail($attendanceId);

        if (! $attendance->in_time) {
            return back()->with('status', [
                'success' => false,
                'msg' => 'TA/DA bill can only be updated for present attendance.',
            ]);
        }

        $attendance->tada_bill_amount = $request->tada_bill_amount;
        $attendance->save();

        return back()->with('status', [
            'success' => true,
            'msg' => 'TA/DA bill updated successfully.',
        ]);
    }

    public function updateHolidayTada(Request $request, $customerId, $holidayDate)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'override_status' => 'required|in:auto,paid,missed',
            'tada_bill_amount' => 'required_if:override_status,paid|numeric|min:0',
        ]);

        try {
            $date = Carbon::createFromFormat('Y-m-d', $holidayDate)->startOfDay();
        } catch (\Throwable $exception) {
            abort(422, 'Invalid holiday date.');
        }

        if ($date->toDateString() !== $holidayDate || ! $date->isFriday()) {
            abort(422, 'Holiday TA/DA can only be adjusted for Friday.');
        }

        $businessId = request()->session()->get('user.business_id');
        $customer = Customer::where('business_id', $businessId)
            ->where('employee_type', Customer::TYPE_SR)
            ->findOrFail($customerId);

        if ($request->override_status === 'auto') {
            CustomerHolidayTadaOverride::where('customer_id', $customer->id)
                ->whereDate('holiday_date', $date->toDateString())
                ->delete();

            $message = 'Friday holiday TA/DA returned to the automatic attendance rule.';
        } else {
            CustomerHolidayTadaOverride::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'holiday_date' => $date->toDateString(),
                ],
                [
                    'status' => $request->override_status,
                    'amount' => $request->override_status === 'paid'
                        ? (float) $request->tada_bill_amount
                        : 0,
                    'adjusted_by' => auth()->id(),
                ]
            );

            $message = 'Friday holiday TA/DA updated successfully.';
        }

        return back()->with('status', [
            'success' => true,
            'msg' => $message,
        ]);
    }

    public function approveLateAttendance($attendanceId)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');
        $attendance = CustomerAttendance::whereHas('customer', function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
                ->where('employee_type', Customer::TYPE_SR);
        })->findOrFail($attendanceId);

        $attendance->late_approval_status = 'approved';
        $attendance->late_approved_by = auth()->id();
        $attendance->late_approved_at = now();
        $attendance->save();

        return back()->with('status', [
            'success' => true,
            'msg' => 'Late attendance approved successfully.',
        ]);
    }

    public function rejectLateAttendance($attendanceId)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');
        $attendance = CustomerAttendance::whereHas('customer', function ($q) use ($businessId) {
            $q->where('business_id', $businessId)
                ->where('employee_type', Customer::TYPE_SR);
        })->findOrFail($attendanceId);

        $attendance->late_approval_status = 'rejected';
        $attendance->late_approved_by = auth()->id();
        $attendance->late_approved_at = now();
        $attendance->save();

        return back()->with('status', [
            'success' => true,
            'msg' => 'Late attendance rejected.',
        ]);
    }
}
