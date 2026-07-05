<?php

namespace App\Http\Requests\External;

use Illuminate\Foundation\Http\FormRequest;

class StoreCounterpartyOrderDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isExternal();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:51200'],
            'type' => ['required', 'string', 'max:64'],
            'requirement_slot_key' => ['required', 'string', 'max:128'],
            'number' => ['nullable', 'string', 'max:128'],
            'document_date' => ['nullable', 'date'],
        ];
    }
}
