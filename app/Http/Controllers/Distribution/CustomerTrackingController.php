<?php

namespace App\Http\Controllers\Distribution;

use App\Customer;
use App\CustomerLocation;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CustomerTrackingController extends Controller
{
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');
        $customers = Customer::where('business_id', $business_id)->active()->orderBy('name')->get();

        return view('distribution.tracking.index', compact('customers'));
    }

    public function getLocations(Request $request): JsonResponse
    {
        $customerId = $request->customer_id;
        $date = $request->date ?? now()->format('Y-m-d');

        $locations = CustomerLocation::where('customer_id', $customerId)
            ->whereDate('recorded_at', $date)
            ->orderBy('recorded_at', 'asc')
            ->get(['latitude', 'longitude', 'recorded_at']);

        return response()->json([
            'success' => true,
            'data' => $locations->map(fn ($l) => [
                'lat' => (float) $l->latitude,
                'lng' => (float) $l->longitude,
                'recorded_at' => $l->recorded_at->toIso8601String(),
            ]),
        ]);
    }

    public function getLatestLocation(Request $request): JsonResponse
    {
        $customerId = $request->customer_id;

        $location = CustomerLocation::where('customer_id', $customerId)
            ->orderBy('recorded_at', 'desc')
            ->first();

        if (! $location) {
            return response()->json(['success' => false, 'message' => 'No location data'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'recorded_at' => $location->recorded_at->toIso8601String(),
            ],
        ]);
    }
}
