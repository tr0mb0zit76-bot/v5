<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'role_ids' => ['nullable', 'array', 'max:10'],
            'role_ids.*' => ['integer', Rule::exists('roles', 'id')],
            'is_active' => ['required', 'boolean'],
            'has_signing_authority' => ['nullable', 'boolean'],
            'belongs_to_management' => ['nullable', 'boolean'],
            'can_management_accounting' => ['nullable', 'boolean'],
            'sees_company_dashboard' => ['nullable', 'boolean'],
            'signing_own_company_ids' => ['nullable', 'array'],
            'signing_own_company_ids.*' => [
                'integer',
                Rule::exists('contractors', 'id')->where(function ($query): void {
                    if (Schema::hasColumn('contractors', 'is_own_company')) {
                        $query->where('is_own_company', true);
                    }
                }),
            ],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_sync_enabled' => ['nullable', 'boolean'],
        ];

        if (Schema::hasTable('departments')) {
            $rules['primary_department_id'] = ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)];
            $rules['approval_department_ids'] = ['nullable', 'array', 'max:20'];
            $rules['approval_department_ids.*'] = ['integer', Rule::exists('departments', 'id')->where('is_active', true)];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'mail_password' => 'пароль почты',
            'mail_sync_enabled' => 'синхронизация почты',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeRoleIdsFromLegacyInput();
    }

    private function mergeRoleIdsFromLegacyInput(): void
    {
        $roleIds = $this->input('role_ids');

        if (! is_array($roleIds) && $this->filled('role_id')) {
            $roleIds = [(int) $this->input('role_id')];
        }

        if (! is_array($roleIds)) {
            return;
        }

        $normalized = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $roleIds),
            static fn (int $id): bool => $id > 0,
        )));

        $this->merge([
            'role_ids' => $normalized,
            'role_id' => $normalized[0] ?? null,
        ]);
    }
}
