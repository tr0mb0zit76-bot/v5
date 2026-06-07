<?php

namespace App\Http\Requests;

use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveContractorPrintFormChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return app(ContractorPrintFormChangeRequestService::class)->canApprovePrintFormChanges($user);
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'string', Rule::in(['approve', 'reject', 'needs_counterparty'])],
            'reason' => [
                Rule::requiredIf(fn (): bool => $this->input('action') === 'reject'),
                'nullable',
                'string',
                'max:2000',
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Укажите действие: approve, reject или needs_counterparty.',
            'reason.required' => 'Укажите причину отклонения.',
        ];
    }
}
