<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MassUpdateLeadsRequest extends FormRequest
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
            'lead_ids' => ['required', 'array', 'min:1'],
            'lead_ids.*' => ['integer', 'distinct', 'exists:leads,id'],
            'action' => ['required', 'string', Rule::in(['source', 'responsible_id', 'status', 'delete'])],
            'value' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'lead_ids.required' => 'Выберите хотя бы один лид.',
            'action.required' => 'Укажите действие.',
            'action.in' => 'Это массовое действие недоступно.',
        ];
    }
}
