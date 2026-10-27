<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShopVisitRequest;
use App\Http\Resources\Api\ShopOwnerResource;
use App\Services\CustomerShopVisitService;
use App\ShopOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShopVisitController extends Controller
{
    public function __construct(
        protected CustomerShopVisitService $visitService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $customer = $request->user();
        $timezone = optional($customer->business)->time_zone ?: config('app.timezone');
        $date = isset($validated['date'])
            ? Carbon::parse($validated['date'], $timezone)
            : Carbon::now($timezone);
        $visitDate = $date->toDateString();
        $visitDay = strtolower($date->englishDayOfWeek);
        $dailyVisits = function ($query) use ($customer, $visitDate) {
            $query->where('customer_id', $customer->id)
                ->where('visit_date', $visitDate);
        };

        $query = ShopOwner::query()
            ->where('distributor_id', $customer->id)
            ->where('visit_day', $visitDay);
        $visitedCount = (clone $query)->whereHas('visits', $dailyVisits)->count();
        $shops = $query->with(['phones', 'visits' => $dailyVisits])
            ->orderBy('road_name')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $shops->getCollection()->map(function (ShopOwner $shop) use ($request, $timezone) {
                $visit = $shop->visits->first();

                return array_merge((new ShopOwnerResource($shop))->toArray($request), [
                    'road_name' => $shop->road_name,
                    'visit_day' => $shop->visit_day,
                    'is_visited' => $visit !== null,
                    'visit_id' => $visit?->id,
                    'visited_at' => $visit
                        ? Carbon::parse($visit->getRawOriginal('visited_at'), $timezone)->toIso8601String()
                        : null,
                    'distance_meters' => $visit?->distance_meters,
                ]);
            }),
            'meta' => [
                'date' => $visitDate,
                'day' => $date->englishDayOfWeek,
                'visit_day' => $visitDay,
                'timezone' => $timezone,
                'current_page' => $shops->currentPage(),
                'last_page' => $shops->lastPage(),
                'per_page' => $shops->perPage(),
                'total' => $shops->total(),
                'visited' => $visitedCount,
                'pending' => $shops->total() - $visitedCount,
            ],
        ]);
    }

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
