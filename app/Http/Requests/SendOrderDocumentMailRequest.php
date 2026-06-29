<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendOrderDocumentMailRequest extends FormRequest
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
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:20000'],
        ];
    }
}
