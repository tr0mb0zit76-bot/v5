<?php

namespace App\Http\Requests;

use App\Rules\DocumentWithinPageBudget;
use App\Support\DocumentUploadBudget;
use App\Support\OrderDocumentRegistryTypes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateDocumentRegistryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'type' => ['required', Rule::in(OrderDocumentRegistryTypes::values())],
            'party' => ['required', Rule::in(['customer', 'carrier', 'contractor', 'internal'])],
            'contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'number' => ['nullable', 'string', 'max:255'],
            'document_date' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'pending', 'signed', 'sent'])],
            'file' => [
                'nullable',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'])
                    ->max(DocumentUploadBudget::absoluteMaxKilobytes()),
                new DocumentWithinPageBudget,
            ],
        ];
    }
}
