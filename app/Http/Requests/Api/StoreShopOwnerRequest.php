<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreShopOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'distributor_id' => ['required', 'integer', 'exists:customers,id'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:shop_owners,email'],
            'phones' => ['required', 'array', 'min:1', 'max:10'],
            'phones.*' => ['required', 'string', 'distinct', 'max:30', 'regex:/^\+?[0-9\s().-]{7,30}$/'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'address' => ['required', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'phones.*.regex' => 'Each phone number must be a valid phone number.',
        ];
    }
}
