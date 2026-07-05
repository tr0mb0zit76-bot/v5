<?php

namespace App\Http\Requests;

use App\Rules\DocumentWithinPageBudget;
use App\Support\DocumentUploadBudget;
use App\Support\OrderDocumentRegistryTypes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreOrderCustomerPortalDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'slot_kind' => ['required', Rule::in(['customer_request', 'customer_closing'])],
            'requirement_slot_key' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(OrderDocumentRegistryTypes::values())],
            'number' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'file' => [
                'required',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'])
                    ->max(DocumentUploadBudget::absoluteMaxKilobytes()),
                new DocumentWithinPageBudget,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Выберите файл.',
            'type.required' => 'Укажите тип документа.',
            'slot_kind.required' => 'Укажите слот документа.',
            'requirement_slot_key.required' => 'Укажите слот документа.',
        ];
    }
}
