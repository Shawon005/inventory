<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CustomerAttendanceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:in,out'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'image' => ['nullable', 'image', 'max:5120'],
            'image_base64' => ['nullable', 'string'],
        ];
    }
}
