<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerSaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? null,
            'quantity' => (float) $this->quantity,
            'purchase_price' => (float) $this->purchase_price,
            'sale_price' => (float) $this->sale_price,
            'profit' => (float) $this->profit,
            'sold_to' => $this->sold_to,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
