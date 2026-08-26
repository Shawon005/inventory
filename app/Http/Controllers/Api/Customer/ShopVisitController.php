<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShopVisitRequest;
use App\Services\CustomerShopVisitService;
use Illuminate\Http\JsonResponse;

class ShopVisitController extends Controller
{
    public function __construct(
        protected CustomerShopVisitService $visitService
    ) {}

    public function store(StoreShopVisitRequest $request): JsonResponse
    {
        $result = $this->visitService->recordNearestVisit(
            $request->user(),
            (float) $request->latitude,
            (float) $request->longitude
        );

        if (! $result['visit']) {
            $nearest = $result['nearest_shop'];

            return response()->json([
                'success' => false,
                'message' => $nearest
                    ? 'You must be within 10 meters of the shop to record a visit.'
                    : 'No shop owner is registered for this sales representative.',
                'data' => [
                    'maximum_distance_meters' => CustomerShopVisitService::MAXIMUM_DISTANCE_METERS,
                    'nearest_shop' => $nearest ? [
                        'id' => $nearest->id,
                        'name' => $nearest->name,
                        'address' => $nearest->address,
                    ] : null,
                    'nearest_distance_meters' => $result['distance_meters'],
                ],
            ], 422);
        }

        $visit = $result['visit'];
        $owner = $result['nearest_shop'];

        return response()->json([
            'success' => true,
            'message' => $result['was_recorded']
                ? 'Shop visit recorded successfully.'
                : 'This shop visit was already recorded today.',
            'data' => [
                'visit_id' => $visit->id,
                'already_visited' => ! $result['was_recorded'],
                'shop_owner' => [
                    'id' => $owner->id,
                    'name' => $owner->name,
                    'address' => $owner->address,
                    'phones' => $owner->phones->pluck('phone')->values(),
                ],
                'distance_meters' => $result['distance_meters'],
                'visited_at' => $visit->visited_at->toIso8601String(),
                'today_visit_count' => $result['today_visit_count'],
            ],
        ], $result['was_recorded'] ? 201 : 200);
    }
}
