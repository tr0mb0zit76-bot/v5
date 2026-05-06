<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AstralEpdWebhookRequest extends FormRequest
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
            'event_id' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:100'],
            'document' => ['required', 'array'],
            'document.crm_document_id' => ['nullable', 'integer'],
            'document.external_id' => ['nullable', 'string', 'max:255'],
            'document.status' => ['required', 'string', 'max:100'],
        ];
    }
}
