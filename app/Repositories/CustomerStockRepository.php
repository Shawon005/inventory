<?php

namespace App\Repositories;
use Illuminate\Support\Collection;
use App\CustomerStock;
use App\Repositories\Contracts\CustomerStockRepositoryInterface;

class CustomerStockRepository implements CustomerStockRepositoryInterface
{
    public function getStockByCustomerAndProduct(int $customerId, int $productId): ?CustomerStock
    {
        return CustomerStock::where('customer_id', $customerId)
            ->where('product_id', $productId)
            ->first();
    }

    public function getCustomerStocks(int $customerId): Collection
    {
        return CustomerStock::where('customer_id', $customerId)
            ->with([
                'product',
                'product.category' => function ($query) {
                    $query->withTrashed();
                },
                'product.sub_category' => function ($query) {
                    $query->withTrashed();
                },
                'variation',
            ])
            ->where('quantity', '>', 0)
            ->get();
    }

    public function updateOrCreate(array $attributes, array $values = []): CustomerStock
    {
        return CustomerStock::updateOrCreate($attributes, $values);
    }
}
