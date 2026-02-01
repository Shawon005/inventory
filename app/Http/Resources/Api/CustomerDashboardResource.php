<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerDashboardResource extends JsonResource
{
    public function toArray($request): array
    {
        $stocks = $this->resource->stocks ?? collect();
        $totalStockValue = $stocks->sum(fn ($s) => $s->quantity * $s->purchase_price);

        return [
            'customer' => new CustomerResource($this->resource),
            'total_stock_items' => $stocks->where('quantity', '>', 0)->count(),
            'total_stock_value' => round($totalStockValue, 2),
            'today_sales_count' => $this->resource->sales()->whereDate('created_at', today())->count(),
            'today_profit' => round($this->resource->sales()->whereDate('created_at', today())->sum('profit'), 2),
        ];
    }
}
