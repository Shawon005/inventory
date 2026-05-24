<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\DistributionLeaveRequest;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        if (! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $businessId = request()->session()->get('user.business_id');

        $customers = Customer::where('business_id', $businessId)->orderBy('name')->get(['id', 'name']);

        $query = DistributionLeaveRequest::with('customer')
            ->where('business_id', $businessId)
            ->orderByDesc('created_at');

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $leaveRequests = $query->paginate(25);

        return view('distribution.leave_requests.index', compact('leaveRequests', 'customers'));
    }

    public function approve(Request $request, $id)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $leave = DistributionLeaveRequest::where('business_id', request()->session()->get('user.business_id'))->findOrFail($id);

        $leave->update([
            'status' => 'approved',
            'admin_note' => $request->admin_note,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('status', ['success' => true, 'msg' => 'Leave request approved.']);
    }

    public function reject(Request $request, $id)
    {
        if (! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'admin_note' => 'nullable|string|max:500',
        ]);

        $leave = DistributionLeaveRequest::where('business_id', request()->session()->get('user.business_id'))->findOrFail($id);

        $leave->update([
            'status' => 'rejected',
            'admin_note' => $request->admin_note,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('status', ['success' => true, 'msg' => 'Leave request rejected.']);
    }
}
