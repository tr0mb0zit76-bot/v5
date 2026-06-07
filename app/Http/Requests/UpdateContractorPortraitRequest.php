<?php

namespace App\Http\Requests;

use App\Support\ContractorPortraitDictionary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContractorPortraitRequest extends FormRequest
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
            'communication_style' => ['nullable', 'string', Rule::in(ContractorPortraitDictionary::communicationStyles())],
            'price_sensitivity' => ['nullable', 'string', Rule::in(ContractorPortraitDictionary::priceSensitivities())],
            'preferred_channel' => ['nullable', 'string', Rule::in(ContractorPortraitDictionary::preferredChannels())],
            'decision_cadence' => ['nullable', 'string', Rule::in(ContractorPortraitDictionary::decisionCadences())],
            'relationship_trust' => ['nullable', 'string', Rule::in(ContractorPortraitDictionary::relationshipTrusts())],
            'success_criteria' => ['nullable', 'string', 'max:5000'],
            'typical_objections' => ['nullable', 'array', 'max:20'],
            'typical_objections.*' => ['string', 'max:120'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
