<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerSellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // 'items' => ['required', 'array', 'min:1'],
            // 'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            // 'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'payment_method' => ['nullable', 'string', 'max:191'],
            'shop_owner_id' => [
                'nullable',
                'integer',
                Rule::exists('shop_owners', 'id')->where(fn ($query) => $query->where('distributor_id', auth()->id())),
            ],
            'sold_to' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:order,ordered,delivered,cancelled,canceled'],
        ];
    }
}
