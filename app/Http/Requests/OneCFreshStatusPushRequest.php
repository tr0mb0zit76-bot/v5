<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OneCFreshStatusPushRequest extends FormRequest
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
            'crm_document_id' => ['required', 'integer'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'pending', 'sent', 'signed'])],
        ];
    }
}
