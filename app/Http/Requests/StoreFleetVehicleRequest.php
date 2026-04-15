<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFleetVehicleRequest extends FormRequest
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
            'owner_contractor_id' => ['required', 'integer', 'exists:contractors,id'],
            'tractor_brand' => ['nullable', 'string', 'max:120'],
            'trailer_brand' => ['nullable', 'string', 'max:120'],
            'tractor_plate' => ['nullable', 'string', 'max:32'],
            'trailer_plate' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
