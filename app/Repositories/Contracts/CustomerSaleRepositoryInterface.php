<?php

namespace App\Repositories\Contracts;

use App\CustomerSale;

interface CustomerSaleRepositoryInterface
{
    public function create(array $data): CustomerSale;

    public function getSalesByCustomer(int $customerId, ?string $fromDate = null, ?string $toDate = null): \Illuminate\Support\Collection;

    public function getSalesSummary(int $customerId, ?string $fromDate = null, ?string $toDate = null): ?object;
}
