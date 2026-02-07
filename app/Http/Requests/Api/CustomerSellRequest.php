<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CustomerSellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sale_price' => ['required', 'numeric', 'min:0'],
            'sold_to' => ['nullable', 'string', 'max:255'],
        ];
    }
}
