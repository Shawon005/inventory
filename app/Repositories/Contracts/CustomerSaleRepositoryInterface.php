<?php

namespace App\Repositories\Contracts;

use App\CustomerSale;

interface CustomerSaleRepositoryInterface
{
    public function create(array $data): CustomerSale;

    public function findByIdAndCustomer(int $id, int $customerId): ?CustomerSale;

    public function update(CustomerSale $sale, array $data): CustomerSale;

    public function delete(CustomerSale $sale): bool;

    public function getSalesByCustomer(int $customerId, ?string $fromDate = null, ?string $toDate = null): \Illuminate\Support\Collection;

    public function getSalesSummary(int $customerId, ?string $fromDate = null, ?string $toDate = null): ?object;

    public function getSalesTotalByDateRange(int $customerId, string $fromDate, string $toDate): float;
}
