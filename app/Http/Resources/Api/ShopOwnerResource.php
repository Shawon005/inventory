<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class ShopOwnerResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'distributor_id' => $this->distributor_id,
            'name' => $this->name,
            'email' => $this->email,
            'phones' => $this->phones->pluck('phone')->values(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'address' => $this->address,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
