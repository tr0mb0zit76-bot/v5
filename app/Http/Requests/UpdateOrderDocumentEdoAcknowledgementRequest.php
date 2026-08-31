<?php

namespace App\Http\Requests;

use App\Support\OrderDocumentClosingFulfillment;
use App\Support\OrderDocumentEpdFulfillment;
use App\Support\OrderDocumentRequestEdoFulfillment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderDocumentEdoAcknowledgementRequest extends FormRequest
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
        $documentTypes = array_values(array_unique([
            ...OrderDocumentClosingFulfillment::CLOSING_TYPES,
            ...OrderDocumentEpdFulfillment::EPD_TYPES,
            ...OrderDocumentRequestEdoFulfillment::REQUEST_TYPES,
        ]));

        return [
            'party' => ['required', Rule::in(['customer', 'carrier', 'contractor', 'internal'])],
            'document_type' => ['required', Rule::in($documentTypes)],
            'slot_key' => ['nullable', 'string', 'max:128'],
            'contractor_id' => ['nullable', 'integer', 'min:1'],
            'received_via_edo' => ['required', 'boolean'],
            'document_number' => [
                Rule::requiredIf(fn (): bool => $this->boolean('received_via_edo')),
                'nullable',
                'string',
                'max:255',
            ],
            'document_date' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'party.required' => 'Укажите сторону документа.',
            'document_type.required' => 'Укажите тип документа.',
            'received_via_edo.required' => 'Укажите статус ЭДО.',
            'document_number.required' => 'Укажите номер документа для отметки ЭДО.',
        ];
    }
}
