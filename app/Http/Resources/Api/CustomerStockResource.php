<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerStockResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->product->name ?? null,
            'quantity' => (float) $this->quantity,
            'purchase_price' => (float) $this->purchase_price,
            'unit' => $this->product->unit->short_name ?? null,
        ];
    }
}
