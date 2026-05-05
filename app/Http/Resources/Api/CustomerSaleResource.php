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
            'items' => json_decode($this->items),
            'product_name' => $this->product->name ?? null,
            'quantity' => (float) $this->quantity,
            'purchase_price' => (float) $this->purchase_price,
            'sale_price' => (float) $this->sale_price,
            'profit' => (float) $this->profit,
            'discount' => (float) ($this->discount ?? 0),
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'sold_to' => $this->sold_to,
            'resaler_address' => $this->resaler_address,
            'resaler_phone' => $this->resaler_phone,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
