<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFleetDriverRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'carrier_contractor_id' => ['required', 'integer', 'exists:contractors,id'],
            'full_name' => ['required', 'string', 'max:255'],
            'passport_series' => ['nullable', 'string', 'max:16'],
            'passport_number' => ['nullable', 'string', 'max:32'],
            'passport_issued_by' => ['nullable', 'string', 'max:500'],
            'passport_issued_at' => ['nullable', 'date'],
            'phone' => ['nullable', 'string', 'max:50'],
            'license_number' => ['nullable', 'string', 'max:64'],
            'license_categories' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
