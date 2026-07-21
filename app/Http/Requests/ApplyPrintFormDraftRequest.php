<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ApplyPrintFormDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessSettingsSystem($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'draft_token' => ['required', 'uuid'],
            'replacements' => ['required', 'array', 'max:500'],
            'replacements.*.find' => ['required', 'string', 'max:2000'],
            'replacements.*.replace' => ['required', 'string', 'max:2000'],
            'replacements.*.enabled' => ['sometimes', 'boolean'],
            'download_filename' => ['nullable', 'string', 'max:255'],
        ];
    }
}
