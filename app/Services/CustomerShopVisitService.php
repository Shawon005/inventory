<?php

namespace App\Services;

use App\Customer;
use App\CustomerShopVisit;
use App\ShopOwner;
use Carbon\Carbon;
use Illuminate\Database\QueryException;

class CustomerShopVisitService
{
    public const MAXIMUM_DISTANCE_METERS = 10.0;

    public function recordNearestVisit(Customer $customer, float $latitude, float $longitude): array
    {
        $nearest = ShopOwner::with('phones')
            ->where('distributor_id', $customer->id)
            ->get()
            ->map(function (ShopOwner $owner) use ($latitude, $longitude) {
                $owner->matched_distance_meters = $this->distanceInMeters(
                    $latitude,
                    $longitude,
                    (float) $owner->latitude,
                    (float) $owner->longitude
                );

                return $owner;
            })
            ->sortBy('matched_distance_meters')
            ->first();

        if (! $nearest || $nearest->matched_distance_meters > self::MAXIMUM_DISTANCE_METERS) {
            return [
                'visit' => null,
                'nearest_shop' => $nearest,
                'distance_meters' => $nearest ? round($nearest->matched_distance_meters, 2) : null,
            ];
        }

        $timezone = optional($customer->business)->time_zone ?: config('app.timezone');
        $visitedAt = Carbon::now($timezone);
        $visitDate = $visitedAt->toDateString();
        $attributes = [
            'customer_id' => $customer->id,
            'shop_owner_id' => $nearest->id,
            'visit_date' => $visitDate,
        ];

        try {
            $visit = CustomerShopVisit::firstOrCreate($attributes, [
                'shop_name' => $nearest->name,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'shop_latitude' => $nearest->latitude,
                'shop_longitude' => $nearest->longitude,
                'distance_meters' => round($nearest->matched_distance_meters, 2),
                'visited_at' => $visitedAt,
            ]);
        } catch (QueryException $exception) {
            // A simultaneous request may create the daily visit after the lookup
            // but before this insert. The unique index makes that request idempotent.
            $visit = CustomerShopVisit::where($attributes)->first();

            if (! $visit) {
                throw $exception;
            }
        }

        return [
            'visit' => $visit,
            'was_recorded' => $visit->wasRecentlyCreated,
            'nearest_shop' => $nearest,
            'distance_meters' => (float) $visit->distance_meters,
            'today_visit_count' => CustomerShopVisit::where('customer_id', $customer->id)
                ->where('visit_date', $visitDate)
                ->count(),
        ];
    }

    public function distanceInMeters(float $latitudeA, float $longitudeA, float $latitudeB, float $longitudeB): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($latitudeB - $latitudeA);
        $longitudeDelta = deg2rad($longitudeB - $longitudeA);
        $a = sin($latitudeDelta / 2) ** 2
            + cos(deg2rad($latitudeA)) * cos(deg2rad($latitudeB))
            * sin($longitudeDelta / 2) ** 2;
        $a = min(1, max(0, $a));

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
