<?php

namespace App\Http\Requests;

use App\Models\ContractorRiskAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmContractorRiskAssessmentRequest extends FormRequest
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
            'assessment_id' => ['required', 'integer', 'exists:contractor_risk_assessments,id'],
            'outcome' => [
                'required',
                'string',
                Rule::in([
                    ContractorRiskAssessment::OUTCOME_ACCEPTED_AS_IS,
                    ContractorRiskAssessment::OUTCOME_ACCEPTED_WITH_EDITS,
                    ContractorRiskAssessment::OUTCOME_REJECTED,
                ]),
            ],
            'applied_debt_limit' => ['nullable', 'numeric', 'min:0'],
            'applied_postpayment_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'schedule_target' => ['nullable', 'string', Rule::in(['customer', 'carrier'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'assessment_id.required' => 'Укажите идентификатор черновика оценки.',
            'outcome.required' => 'Укажите результат подтверждения.',
            'outcome.in' => 'Недопустимый результат подтверждения.',
        ];
    }
}
