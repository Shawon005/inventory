<?php

namespace App\Http\Controllers\Api\Customer;

use App\DistributionLeaveRequest;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customer = auth()->user();

        $query = DistributionLeaveRequest::where('business_id', $customer->business_id)
            ->where('customer_id', $customer->id)
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $rows = $query->get();

        return response()->json([
            'success' => true,
            'data' => $rows->map(function ($row) {
                return [
                    'id' => $row->id,
                    'from_date' => optional($row->from_date)->toDateString(),
                    'to_date' => optional($row->to_date)->toDateString(),
                    'days_count' => (int) $row->days_count,
                    'reason' => $row->reason,
                    'status' => $row->status,
                    'admin_note' => $row->admin_note,
                    'approved_at' => optional($row->approved_at)->toIso8601String(),
                    'created_at' => optional($row->created_at)->toIso8601String(),
                ];
            })->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $customer = auth()->user();

        $request->validate([
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'reason' => 'nullable|string|max:500',
        ]);

        $from = Carbon::parse($request->from_date)->startOfDay();
        $to = Carbon::parse($request->to_date)->startOfDay();

        $hasOverlap = DistributionLeaveRequest::where('business_id', $customer->business_id)
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('from_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhereBetween('to_date', [$from->toDateString(), $to->toDateString()])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->where('from_date', '<=', $from->toDateString())
                            ->where('to_date', '>=', $to->toDateString());
                    });
            })
            ->exists();

        if ($hasOverlap) {
            return response()->json([
                'success' => false,
                'message' => 'A pending/approved leave already exists in this date range.',
            ], 422);
        }

        $daysCount = $from->diffInDays($to) + 1;

        $leave = DistributionLeaveRequest::create([
            'business_id' => $customer->business_id,
            'customer_id' => $customer->id,
            'from_date' => $from->toDateString(),
            'to_date' => $to->toDateString(),
            'days_count' => $daysCount,
            'reason' => $request->reason,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Leave request submitted successfully.',
            'data' => [
                'id' => $leave->id,
                'status' => $leave->status,
                'from_date' => $leave->from_date->toDateString(),
                'to_date' => $leave->to_date->toDateString(),
                'days_count' => (int) $leave->days_count,
            ],
        ]);
    }
}
