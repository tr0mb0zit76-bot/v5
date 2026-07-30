<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFleetContainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $number = $this->input('container_number');
        if (is_string($number)) {
            $this->merge([
                'container_number' => mb_strtoupper(preg_replace('/\s+/', '', trim($number)) ?? ''),
            ]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'owner_contractor_id' => ['required', 'integer', 'exists:contractors,id'],
            'container_number' => ['required', 'string', 'max:32'],
            'size_code' => ['nullable', 'string', Rule::in(['20', '40', '40HC', '45', 'other'])],
            'container_type' => [
                'nullable',
                'string',
                Rule::in(['dry', 'reefer', 'open_top', 'flat_rack', 'tank', 'other']),
            ],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'owner_contractor_id.required' => 'Укажите владельца контейнера.',
            'container_number.required' => 'Укажите номер контейнера.',
        ];
    }
}
