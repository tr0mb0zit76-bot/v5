<?php

namespace App\Http\Requests;

use App\Support\ContractorIdentity;
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
        if ($this->has('name')) {
            $this->merge(['name' => ContractorIdentity::normalizeName($this->input('name'))]);
        }

        if ($this->has('inn')) {
            $this->merge(['inn' => ContractorIdentity::normalizeInn($this->input('inn'))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', Rule::unique('contractors', 'name')],
            'inn' => ['nullable', 'string', 'max:20', Rule::unique('contractors', 'inn')],
            'kpp' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['customer', 'carrier', 'contractor', 'both'])],
        ];
    }
}
