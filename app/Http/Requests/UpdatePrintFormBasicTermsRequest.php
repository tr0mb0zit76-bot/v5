<?php

namespace App\Http\Requests;

use App\Models\PrintFormBasicTerm;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdatePrintFormBasicTermsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessSettingsSystem($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $contractorRules = ['nullable', 'integer'];

        if (Schema::hasTable('contractors')) {
            $contractorRules[] = Rule::exists('contractors', 'id');
        }

        return [
            'party' => ['required', 'string', Rule::in([
                PrintFormBasicTerm::PARTY_CUSTOMER,
                PrintFormBasicTerm::PARTY_CARRIER,
            ])],
            'contractor_id' => $contractorRules,
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
            'contractor_id.exists' => 'Выбранный контрагент не найден.',
            'items.*.max' => 'Текст пункта не должен превышать 8000 символов.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('contractor_id') && $this->input('contractor_id') === '') {
            $this->merge(['contractor_id' => null]);
        }
    }
}
