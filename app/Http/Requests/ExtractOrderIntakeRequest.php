<?php

namespace App\Http\Requests;

use App\Rules\DocumentWithinPageBudget;
use App\Support\DocumentUploadBudget;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ExtractOrderIntakeRequest extends FormRequest
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
            'file' => [
                'required',
                File::types(['pdf', 'docx', 'jpg', 'jpeg', 'png', 'webp'])
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
            'file.required' => 'Выберите файл заявки.',
            'file.mimes' => 'Поддерживаются PDF, DOCX и изображения.',
        ];
    }
}
