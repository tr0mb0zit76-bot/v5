<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadNextStepRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('responsible_id') === '' || $this->input('responsible_id') === null) {
            $this->merge(['responsible_id' => $this->user()?->id]);
        }

        if ($this->input('due_at') === '') {
            $this->merge(['due_at' => null]);
        }
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks')) {
            return false;
        }

        $scope = RoleAccess::resolveVisibilityScope($user->role?->name, $user->role?->visibility_scopes, 'tasks');

        if ($scope === 'own' && (int) $this->input('responsible_id') !== (int) $user->id) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'responsible_id' => ['required', 'integer', 'exists:users,id'],
            'priority' => ['nullable', 'string', 'in:low,medium,high,critical'],
        ];
    }
}
