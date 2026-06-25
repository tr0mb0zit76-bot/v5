<?php

namespace App\Http\Requests;

use App\Services\Fleet\FleetVehicleRegistry;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFleetVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        /** @var FleetVehicleRegistry $registry */
        $registry = app(FleetVehicleRegistry::class);

        $this->merge([
            'tractor_plate' => $registry->normalizePlate($this->input('tractor_plate')),
            'trailer_plate' => $registry->normalizePlate($this->input('trailer_plate')),
        ]);
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
