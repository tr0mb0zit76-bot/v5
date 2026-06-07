<?php

namespace App\Http\Requests;

use App\Models\PrintFormBasicTerm;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractorPrintFormBasicTermsRequest extends FormRequest
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
            'items' => ['present', 'array'],
            'items.*' => ['nullable', 'string', 'max:8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party.required' => 'Укажите сторону (заказчик или перевозчик).',
            'party.in' => 'Недопустимая сторона для базовых условий.',
            'items.*.max' => 'Текст пункта не должен превышать 8000 символов.',
        ];
    }
}
