<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class CustomerSaleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'shop_owner_id' => $this->shop_owner_id,
            'product_id' => $this->product_id,
            'items' => json_decode($this->items),
            'product_name' => $this->product->name ?? null,
            'quantity' => (float) $this->quantity,
            'purchase_price' => (float) $this->purchase_price,
            'sale_price' => (float) $this->sale_price,
            'order_amount' => $this->order_amount,
            'delivery_amount' => $this->delivery_amount,
            'return_amount' => (float) ($this->return_amount ?? 0),
            'total' => $this->total_amount,
            'return_reason' => $this->return_reason,
            'return_status' => $this->return_status,
            'returned_at' => optional($this->returned_at)->toIso8601String(),
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
