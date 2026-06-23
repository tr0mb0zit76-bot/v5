<?php

namespace App\Http\Requests;

use App\Models\Contractor;
use App\Models\ContractorRiskAssessment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfirmContractorRiskAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $debtLimit = $this->input('applied_debt_limit');
        $postpaymentDays = $this->input('applied_postpayment_days');

        $this->merge([
            'applied_debt_limit' => $debtLimit === '' || $debtLimit === null ? null : $debtLimit,
            'applied_postpayment_days' => $postpaymentDays === '' || $postpaymentDays === null
                ? null
                : (int) round((float) $postpaymentDays),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Contractor|null $contractor */
        $contractor = $this->route('contractor');

        return [
            'assessment_id' => [
                'required',
                'integer',
                Rule::exists('contractor_risk_assessments', 'id')
                    ->where(fn ($query) => $contractor instanceof Contractor
                        ? $query->where('contractor_id', $contractor->id)
                        : $query),
            ],
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
            'assessment_id.exists' => 'Черновик оценки не найден для этого контрагента.',
            'outcome.required' => 'Укажите результат подтверждения.',
            'outcome.in' => 'Недопустимый результат подтверждения.',
            'applied_debt_limit.numeric' => 'Лимит задолженности должен быть числом.',
            'applied_postpayment_days.integer' => 'Отсрочка должна быть целым числом дней.',
        ];
    }
}
