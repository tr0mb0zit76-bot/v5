<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdvanceSalesScriptPlaySessionRequest extends FormRequest
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
            'sales_script_reaction_class_id' => ['nullable', 'integer', 'exists:sales_script_reaction_classes,id'],
            'compound' => ['sometimes', 'boolean'],
            'field_values' => ['nullable', 'array'],
            'field_values.*' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
