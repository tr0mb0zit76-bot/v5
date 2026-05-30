<?php

namespace App\Http\Requests;

use App\Rules\DocumentWithinPageBudget;
use App\Support\DocumentUploadBudget;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreOrderCarrierPortalFleetDocumentRequest extends FormRequest
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
            'fleet_target' => ['required', Rule::in(['vehicle', 'driver'])],
            'document_type' => ['required', 'string', 'max:50'],
            'tractor_plate' => ['nullable', 'string', 'max:32'],
            'trailer_plate' => ['nullable', 'string', 'max:32'],
            'tractor_brand' => ['nullable', 'string', 'max:120'],
            'trailer_brand' => ['nullable', 'string', 'max:120'],
            'driver_full_name' => ['nullable', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:64'],
            'driver_license' => ['nullable', 'string', 'max:64'],
            'file' => [
                'required',
                File::types(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'webp'])
                    ->max(DocumentUploadBudget::absoluteMaxKilobytes()),
                new DocumentWithinPageBudget,
            ],
        ];
    }
}
