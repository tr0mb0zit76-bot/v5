<?php

namespace App\Http\Requests;

use App\Models\Role;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
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
        /** @var Role $role */
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_]+$/', Rule::unique('roles', 'name')->ignore($role->id)],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in(RoleAccess::permissionKeys())],
            'visibility_areas' => ['required', 'array'],
            'visibility_areas.*' => ['string', Rule::in(RoleAccess::visibilityAreaKeys())],
            'visibility_scopes' => ['nullable', 'array'],
            'visibility_scopes.*' => ['array'],
            'visibility_scopes.*.mode' => ['required_with:visibility_scopes', 'string', Rule::in(array_column(RoleAccess::visibilityScopeOptions(), 'value'))],
            'has_signing_authority' => ['nullable', 'boolean'],
        ];
    }
}
