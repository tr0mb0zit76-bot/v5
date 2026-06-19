<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Foundation\Http\FormRequest;

class FreezeBudgetPlanSnapshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessBudgeting($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_label' => ['required', 'string', 'max:120'],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_label.required' => 'Укажите название периода плана.',
            'period_start.required' => 'Укажите начало периода.',
            'period_end.required' => 'Укажите конец периода.',
            'period_end.after_or_equal' => 'Конец периода не может быть раньше начала.',
        ];
    }
}
