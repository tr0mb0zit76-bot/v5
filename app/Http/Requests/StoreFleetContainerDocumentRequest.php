<?php

namespace App\Http\Requests;

use App\Rules\DocumentWithinPageBudget;
use App\Support\DocumentUploadBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFleetContainerDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'document_type' => [
                'required',
                'string',
                Rule::in(['ownership', 'lease_contract', 'csc_plate', 'other']),
            ],
            'file' => [
                'required',
                'file',
                'max:'.DocumentUploadBudget::absoluteMaxKilobytes(),
                new DocumentWithinPageBudget,
            ],
        ];
    }
}
