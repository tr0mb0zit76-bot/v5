<?php

namespace App\Http\Requests;

use App\Support\McpIntegrationCatalog;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMcpDataLinksRequest extends FormRequest
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
        $nodeKeys = McpIntegrationCatalog::nodeKeys();

        return [
            'links' => ['present', 'array'],
            'links.*.source_key' => ['required', 'string', Rule::in($nodeKeys)],
            'links.*.target_key' => ['required', 'string', Rule::in($nodeKeys)],
            'links.*.bidirectional' => ['nullable', 'boolean'],
            'links.*.is_active' => ['nullable', 'boolean'],
            'links.*.label' => ['nullable', 'string', 'max:255'],
            'links.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
