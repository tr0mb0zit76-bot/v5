<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProposalHtmlTemplateRequest extends FormRequest
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
        $templateId = $this->route('proposalHtmlTemplate')?->id ?? $this->route('proposalHtmlTemplate');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('proposal_html_templates', 'slug')->ignore($templateId),
            ],
            'is_active' => ['sometimes', 'boolean'],
            'html_body' => ['sometimes', 'required', 'string'],
            'css_inline' => ['nullable', 'string'],
            'visibility' => ['sometimes', 'string', Rule::in(['private', 'role', 'workspace'])],
        ];
    }
}
