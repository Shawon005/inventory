<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\CustomerAttendance;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');
        $customers = Customer::where('business_id', $businessId)->orderBy('name')->get(['id', 'name']);

        $query = CustomerAttendance::with('customer')
            ->whereHas('customer', function ($q) use ($businessId) {
                $q->where('business_id', $businessId);
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

        return view('distribution.attendance.index', compact('attendances', 'customers'));
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
            $q->where('business_id', $businessId);
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

    public function approveLateAttendance($attendanceId)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');
        $attendance = CustomerAttendance::whereHas('customer', function ($q) use ($businessId) {
            $q->where('business_id', $businessId);
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
            $q->where('business_id', $businessId);
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
