<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProposalHtmlTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', 'unique:proposal_html_templates,slug'],
            'is_active' => ['sometimes', 'boolean'],
            'html_body' => ['required', 'string'],
            'css_inline' => ['nullable', 'string'],
            'visibility' => ['sometimes', 'string', Rule::in(['private', 'role', 'workspace'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название шаблона.',
            'html_body.required' => 'Тело шаблона не может быть пустым.',
        ];
    }
}
