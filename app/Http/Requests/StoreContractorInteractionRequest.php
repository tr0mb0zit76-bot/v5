<?php

namespace App\Http\Requests;

use App\Support\ContractorPortraitDictionary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractorInteractionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contractor_contact_id' => ['nullable', 'integer', 'exists:contractor_contacts,id'],
            'contacted_at' => ['required', 'date'],
            'channel' => ['required', 'string', Rule::in(['phone', 'email', 'messenger', 'meeting'])],
            'outcome_code' => ['required', 'string', Rule::in(ContractorPortraitDictionary::interactionOutcomes())],
            'next_contact_at' => ['nullable', 'date'],
            'subject' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:5000'],
            'result' => ['nullable', 'string', 'max:2000'],
            'objection_tags' => ['nullable', 'array', 'max:10'],
            'objection_tags.*' => ['string', Rule::in(ContractorPortraitDictionary::objectionTags())],
            'merge_to_portrait' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'summary.required' => 'Кратко опишите итог контакта.',
            'outcome_code.required' => 'Укажите исход контакта.',
        ];
    }
}
