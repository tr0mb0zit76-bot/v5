<?php

namespace App\Http\Requests;

use App\Models\PrintFormBasicTerm;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitContractorPrintFormChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && RoleAccess::canAccessVisibilityArea($user, 'contractors');
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'party' => ['required', 'string', Rule::in([
                PrintFormBasicTerm::PARTY_CUSTOMER,
                PrintFormBasicTerm::PARTY_CARRIER,
            ])],
            'items' => ['required', 'array', 'min:1'],
            'items.*' => ['required', 'string', 'max:8000'],
            'manager_notes' => ['nullable', 'string', 'max:5000'],
            'yurik_summary' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party.required' => 'Укажите сторону (заказчик или перевозчик).',
            'items.required' => 'Добавьте хотя бы один пункт базовых условий.',
            'items.min' => 'Добавьте хотя бы один пункт базовых условий.',
        ];
    }
}
