<?php

namespace App\Http\Requests;

use App\Support\MailSync\MailOutboundAttachmentRules;
use App\Support\VisibleOrderScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendCommercialMailRequest extends FormRequest
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
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'order_id' => ['nullable', 'integer', Rule::exists('orders', 'id')->where(function ($query): void {
                VisibleOrderScope::apply($query);
            })],
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['required', 'email'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            ...MailOutboundAttachmentRules::validationRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.required' => 'Укажите адрес получателя.',
            'to.*.email' => 'Некорректный e-mail получателя.',
            'subject.required' => 'Укажите тему письма.',
            'body.required' => 'Укажите текст письма.',
        ];
    }
}
