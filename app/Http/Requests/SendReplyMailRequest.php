<?php

namespace App\Http\Requests;

use App\Support\MailSync\MailOutboundAttachmentRules;
use Illuminate\Foundation\Http\FormRequest;

class SendReplyMailRequest extends FormRequest
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
            'to' => ['required', 'array', 'min:1'],
            'to.*' => ['required', 'email'],
            'cc' => ['nullable', 'array'],
            'cc.*' => ['email'],
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
            'body.required' => 'Укажите текст ответа.',
        ];
    }
}
