<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendLeadHtmlProposalMailRequest extends FormRequest
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
            'proposal_html_template_id' => ['required', 'integer', 'exists:proposal_html_templates,id'],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['required', 'email'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proposal_html_template_id.required' => 'Выберите HTML-шаблон КП.',
            'to.required' => 'Укажите адрес получателя.',
            'subject.required' => 'Укажите тему письма.',
            'body.required' => 'Укажите текст письма.',
        ];
    }
}
