<?php

namespace App\Http\Requests;

use App\Services\SalaryPayrollService;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalaryPeriodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->routeIs('finance.salary.*')) {
            return RoleAccess::canAccessFinanceSalary($this->user());
        }

        return RoleAccess::canAccessSettingsMotivation($this->user());
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('period_type') || ! $this->filled('period_start')) {
            return;
        }

        $normalized = app(SalaryPayrollService::class)->normalizeHalfMonthDates(
            (string) $this->input('period_type'),
            (string) $this->input('period_start'),
        );

        $this->merge($normalized);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $periodType = (string) $this->input('period_type');
            $periodStart = (string) $this->input('period_start');

            if ($periodType === '' || $periodStart === '') {
                return;
            }

            $existing = app(SalaryPayrollService::class)->findPeriodForMonthAndType($periodType, $periodStart);

            if ($existing !== null) {
                $validator->errors()->add(
                    'period_start',
                    'Период с таким типом (H1/H2) и месяцем уже существует. Выберите его в списке или удалите лишний черновик.'
                );
            }
        });
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'period_type' => ['required', 'string', Rule::in(['h1', 'h2'])],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'period_start.required' => 'Укажите дату начала периода.',
            'period_end.required' => 'Укажите дату окончания периода.',
            'period_type.required' => 'Выберите тип периода (H1 или H2).',
        ];
    }
}
