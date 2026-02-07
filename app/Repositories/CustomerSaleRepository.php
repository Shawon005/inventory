<?php

namespace App\Repositories;

use App\CustomerSale;
use App\Repositories\Contracts\CustomerSaleRepositoryInterface;
use Illuminate\Support\Collection;
class CustomerSaleRepository implements CustomerSaleRepositoryInterface
{
    public function create(array $data): CustomerSale
    {
        return CustomerSale::create($data);
    }

    public function getSalesByCustomer(int $customerId, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $query = CustomerSale::where('customer_id', $customerId)
            ->with(['product', 'product.unit'])
            ->orderBy('created_at', 'desc');

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query->get();
    }

    public function getSalesSummary(int $customerId, ?string $fromDate = null, ?string $toDate = null): Collection
    {
        $query = CustomerSale::where('customer_id', $customerId);

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        return $query->selectRaw('
            COUNT(*) as total_sales,
            SUM(quantity) as total_quantity,
            SUM(sale_price * quantity) as total_revenue,
            SUM(profit) as total_profit
        ')->first();
    }
}
