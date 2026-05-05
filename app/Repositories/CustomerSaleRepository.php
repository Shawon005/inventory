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

    public function findByIdAndCustomer(int $id, int $customerId): ?CustomerSale
    {
        return CustomerSale::where('id', $id)
            ->where('customer_id', $customerId)
            ->first();
    }

    public function update(CustomerSale $sale, array $data): CustomerSale
    {
        $sale->fill($data);
        $sale->save();

        return $sale;
    }

    public function delete(CustomerSale $sale): bool
    {
        return (bool) $sale->delete();
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

    public function getSalesTotalByDateRange(int $customerId, string $fromDate, string $toDate): float
    {
        return (float) CustomerSale::where('customer_id', $customerId)
            ->whereDate('created_at', '>=', $fromDate)
            ->whereDate('created_at', '<=', $toDate)
            ->sum('sale_price');
    }
}
