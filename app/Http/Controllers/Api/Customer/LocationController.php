<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CustomerLocationUpdateRequest;
use App\Services\CustomerLocationService;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function __construct(
        protected CustomerLocationService $customerLocationService
    ) {}

    /**
     * Update customer GPS location.
     */
    public function update(CustomerLocationUpdateRequest $request): JsonResponse
    {
        $location = $this->customerLocationService->updateLocation(
            auth()->id(),
            $request->latitude,
            $request->longitude
        );

        return response()->json([
            'success' => true,
            'message' => 'Location updated',
            'data' => [
                'latitude' => $location->latitude,
                'longitude' => $location->longitude,
                'recorded_at' => $location->recorded_at->toIso8601String(),
            ],
        ]);
    }
}
