<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesScriptPlaySessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'sales_script_version_id' => ['required', 'integer', 'exists:sales_script_versions,id'],
            'contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'return_to' => ['nullable', 'string', Rule::in(['trainer'])],
            'trainer_profile_key' => ['nullable', 'string', 'max:100', Rule::requiredIf(fn (): bool => $this->input('return_to') === 'trainer')],
            'trainer_profile_title' => ['nullable', 'string', 'max:160', Rule::requiredIf(fn (): bool => $this->input('return_to') === 'trainer')],
            'trainer_profile_context' => ['nullable', 'string', 'max:4000', Rule::requiredIf(fn (): bool => $this->input('return_to') === 'trainer')],
            'training_role_mode' => ['nullable', 'string', Rule::in(['manager_seller', 'manager_buyer'])],
        ];
    }
}
