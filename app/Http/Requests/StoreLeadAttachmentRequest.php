<?php

namespace App\Http\Requests;

use App\Rules\DocumentWithinPageBudget;
use App\Support\DocumentUploadBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreLeadAttachmentRequest extends FormRequest
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
            'file' => [
                'required',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg', 'webp', 'txt'])
                    ->max(DocumentUploadBudget::absoluteMaxKilobytes()),
                new DocumentWithinPageBudget,
            ],
        ];
    }
}
