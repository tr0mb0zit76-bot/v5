<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks')) {
            return false;
        }

        if ($this->string('action')->toString() === 'assign') {
            return RoleAccess::canBulkMutateTasks($user);
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'action' => ['required', 'string', Rule::in(['close', 'assign'])],
            'responsible_id' => ['required_if:action,assign', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
