<?php

namespace Modules\Manufacturing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ManufacturingRequest extends FormRequest
{
    public function authorize(): bool { return $this->user() !== null; }

    protected function prepareForValidation()
    {
        if ($this->header('Idempotency-Key')) {
            $this->merge(['idempotency_key' => $this->header('Idempotency-Key')]);
        }
    }

    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'string', 'regex:/^[A-Za-z0-9_.:-]{8,80}$/D']];
    }

    public function operationKey(): string { return $this->validated()['idempotency_key']; }
    public function payload(): array { return $this->except(['idempotency_key', '_token', '_method']); }
}
