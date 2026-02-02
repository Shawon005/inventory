<?php

namespace App\Repositories\Contracts;

use App\CustomerStock;
use Illuminate\Support\Collection;

interface CustomerStockRepositoryInterface
{
    public function getStockByCustomerAndProduct(int $customerId, int $productId): ?CustomerStock;

    public function getCustomerStocks(int $customerId): \Illuminate\Support\Collection;

    public function updateOrCreate(array $attributes, array $values = []): CustomerStock;
}
