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
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:50'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'is_active' => ['required', 'boolean'],
            'has_signing_authority' => ['nullable', 'boolean'],
            'signing_own_company_ids' => ['nullable', 'array'],
            'signing_own_company_ids.*' => [
                'integer',
                Rule::exists('contractors', 'id')->where(function ($query): void {
                    if (Schema::hasColumn('contractors', 'is_own_company')) {
                        $query->where('is_own_company', true);
                    }
                }),
            ],
        ];
    }
}
