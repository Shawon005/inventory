<?php

namespace App\Repositories\Contracts;
use Illuminate\Support\Collection;
use App\CustomerLocation;

interface CustomerLocationRepositoryInterface
{
    public function create(array $data): CustomerLocation;

    public function getLatestByCustomer(int $customerId): ?CustomerLocation;

    public function getLocationsByCustomerAndDate(int $customerId, string $date): \Illuminate\Support\Collection;

    public function getLocationsByCustomerAndDateRange(int $customerId, string $fromDate, string $toDate): Collection;
}
