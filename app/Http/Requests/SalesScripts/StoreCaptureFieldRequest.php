<?php

namespace App\Http\Requests\SalesScripts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCaptureFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('sales_script_capture_fields', 'code')],
            'label' => ['required', 'string', 'max:120'],
            'value_type' => ['nullable', 'string', 'max:32', Rule::in(['text'])],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'Код поля: латиница, цифры и подчёркивание, с буквы.',
        ];
    }
}
