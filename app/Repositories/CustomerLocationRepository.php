<?php

namespace App\Repositories;
use Illuminate\Support\Collection;
use App\CustomerLocation;
use App\Repositories\Contracts\CustomerLocationRepositoryInterface;

class CustomerLocationRepository implements CustomerLocationRepositoryInterface
{
    public function create(array $data): CustomerLocation
    {
        return CustomerLocation::create($data);
    }

    public function getLatestByCustomer(int $customerId): ?CustomerLocation
    {
        return CustomerLocation::where('customer_id', $customerId)
            ->orderBy('recorded_at', 'desc')
            ->first();
    }

    public function getLocationsByCustomerAndDate(int $customerId, string $date): Collection
    {
        return CustomerLocation::where('customer_id', $customerId)
            ->whereDate('recorded_at', $date)
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    public function getLocationsByCustomerAndDateRange(int $customerId, string $fromDate, string $toDate): Collection
    {
        return CustomerLocation::where('customer_id', $customerId)
            ->whereBetween('recorded_at', [$fromDate, $toDate])
            ->orderBy('recorded_at', 'asc')
            ->get();
    }
}
