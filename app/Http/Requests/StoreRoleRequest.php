<?php

namespace App\Http\Requests;

use App\Support\MobileNavCatalog;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(RoleAccess::permissionKeys())],
            'visibility_areas' => ['required', 'array', 'min:1'],
            'visibility_areas.*' => ['string', Rule::in(RoleAccess::visibilityAreaKeys())],
            'visibility_scopes' => ['nullable', 'array'],
            'visibility_scopes.*' => ['array'],
            'visibility_scopes.*.mode' => ['required_with:visibility_scopes', 'string', Rule::in(array_column(RoleAccess::visibilityScopeOptions(), 'value'))],
            'has_signing_authority' => ['nullable', 'boolean'],
            'default_mobile_nav_keys' => ['sometimes', 'nullable', 'array', 'max:'.MobileNavCatalog::MAX_SELECTABLE],
            'default_mobile_nav_keys.*' => ['string', Rule::in(MobileNavCatalog::validKeys())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visibility_areas.min' => 'Нужно оставить хотя бы одну область видимости.',
        ];
    }
}
