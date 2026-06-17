<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use App\Support\TaskStatus;
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

        if ($this->string('action')->toString() === 'delete') {
            return RoleAccess::canDeleteTask($user);
        }

        return RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', 'exists:tasks,id'],
            'action' => ['required', 'string', Rule::in(['close', 'assign', 'status', 'reschedule', 'delete'])],
            'responsible_id' => ['required_if:action,assign', 'nullable', 'integer', 'exists:users,id'],
            'status' => ['required_if:action,status', 'nullable', 'string', Rule::in(TaskStatus::values())],
            'due_at' => ['required_if:action,reschedule', 'nullable', 'date'],
        ];
    }
}
