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
            ->orderByDesc('attendance_time');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->date);
        }

        $attendances = $query->paginate(25);

        return view('distribution.attendance.index', compact('attendances', 'customers'));
    }
}
