<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use App\Support\TaskStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaskRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $leadId = $this->input('lead_id');

        if ($leadId === '' || $leadId === null) {
            $this->merge(['lead_id' => null]);
        }

        $sla = $this->input('sla_deadline_at');
        if ($sla === '') {
            $this->merge(['sla_deadline_at' => null]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', 'string', Rule::in(['low', 'medium', 'high', 'critical'])],
            'status' => ['nullable', 'string', Rule::in(TaskStatus::values())],
            'due_at' => ['nullable', 'date'],
            'sla_deadline_at' => ['nullable', 'date'],
            'responsible_id' => ['required', 'integer', 'exists:users,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'order_id' => ['nullable', 'integer'],
            'contractor_id' => ['nullable', 'integer'],
        ];
    }
}
