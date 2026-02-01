<?php

namespace App\Repositories\Contracts;

use App\CustomerStock;

interface CustomerStockRepositoryInterface
{
    public function getStockByCustomerAndProduct(int $customerId, int $productId): ?CustomerStock;

    public function getCustomerStocks(int $customerId): \Illuminate\Support\Collection;

    public function updateOrCreate(array $attributes, array $values = []): CustomerStock;
}
