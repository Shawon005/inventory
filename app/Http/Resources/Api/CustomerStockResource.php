<?php

namespace App\Http\Resources\Api;

use App\Category;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerStockResource extends JsonResource
{
    public function toArray($request): array
    {
        $product = $this->product;
        
        $categoryId = $product->category_id ?? null;
        $categoryName = $product->category->name ?? null;

        // Fallback: some products may only have sub-category assigned.
        if (empty($categoryId) && !empty($product->sub_category_id)) {
            $categoryId = $product->sub_category_id;
            $categoryName = $product->sub_category->name ?? null;
        }

        // Fallback: in case relation is missing, fetch category (including soft-deleted).
        if (!empty($categoryId) && empty($categoryName)) {
            $categoryName = Category::withTrashed()->where('id', $categoryId)->value('name');
        }

        return [
            'product_name' => $product->name ?? null,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'quantity' => (float) $this->quantity,
            'purchase_price' => (float) ($this->variation->default_sell_price ?? 0),
        ];
    }
}
