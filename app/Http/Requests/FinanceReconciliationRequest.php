<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class FinanceReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canViewPaymentSchedules($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contractor_id' => ['required', 'integer', 'exists:contractors,id'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contractor_id.required' => 'Выберите контрагента.',
            'contractor_id.exists' => 'Контрагент не найден.',
            'date_to.after_or_equal' => 'Дата окончания не может быть раньше даты начала.',
        ];
    }
}
