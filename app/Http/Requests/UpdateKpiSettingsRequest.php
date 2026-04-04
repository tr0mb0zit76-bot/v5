<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKpiSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'bonus_multiplier' => ['required', 'numeric', 'min:0', 'max:100'],
            'thresholds' => ['required', 'array', 'min:1'],
            'thresholds.*.threshold_from' => ['required', 'numeric', 'min:0', 'max:1'],
            'thresholds.*.threshold_to' => ['required', 'numeric', 'min:0', 'max:1'],
            'thresholds.*.direct_kpi' => ['required', 'integer', 'min:0', 'max:100'],
            'thresholds.*.indirect_kpi' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
