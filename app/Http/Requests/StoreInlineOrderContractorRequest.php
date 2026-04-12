<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInlineOrderContractorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->truncateStringFields([
            'name' => 255,
            'inn' => 20,
            'kpp' => 20,
            'address' => 255,
            'phone' => 50,
            'email' => 255,
            'contact_person' => 255,
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:20'],
            'kpp' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['customer', 'carrier', 'both'])],
        ];
    }

    /**
     * @param  array<string, int>  $fields
     */
    private function truncateStringFields(array $fields): void
    {
        foreach ($fields as $field => $maxLength) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === null || $value === '') {
                continue;
            }

            $stringValue = trim((string) $value);

            if (mb_strlen($stringValue) <= $maxLength) {
                $this->merge([$field => $stringValue]);

                continue;
            }

            $this->merge([$field => mb_substr($stringValue, 0, $maxLength)]);
        }
    }
}
