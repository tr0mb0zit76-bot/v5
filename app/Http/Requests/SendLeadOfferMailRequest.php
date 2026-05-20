<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendLeadOfferMailRequest extends FormRequest
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
            'to.required' => 'Укажите адрес получателя.',
            'subject.required' => 'Укажите тему письма.',
            'body.required' => 'Укажите текст письма.',
        ];
    }
}
