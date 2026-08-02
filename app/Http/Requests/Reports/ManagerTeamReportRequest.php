<?php

declare(strict_types=1);

namespace App\Http\Requests\Reports;

use App\Services\Reports\ManagerTeamMetricCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ManagerTeamReportRequest extends FormRequest
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
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'tab' => ['nullable', 'string', 'max:32'],
            'party' => ['nullable', 'string', 'in:customer,carrier'],
            'stuck_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'managers_mode' => ['nullable', 'string', Rule::in(ManagerTeamMetricCatalog::modes())],
            'user_ids' => ['nullable', 'array', 'max:100'],
            'user_ids.*' => ['integer', 'min:1'],
            'department_id' => ['nullable', 'integer', 'min:1'],
            'metrics' => ['nullable', 'array', 'max:50'],
            'metrics.*' => ['string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $userIds = $this->input('user_ids');
        if (is_string($userIds)) {
            $parts = preg_split('/\s*,\s*/', $userIds) ?: [];
            $this->merge([
                'user_ids' => array_values(array_filter(array_map('intval', $parts))),
            ]);
        }

        $metrics = $this->input('metrics');
        if (is_string($metrics)) {
            $parts = preg_split('/\s*,\s*/', $metrics) ?: [];
            $this->merge([
                'metrics' => array_values(array_filter(array_map('strval', $parts))),
            ]);
        }
    }
}
