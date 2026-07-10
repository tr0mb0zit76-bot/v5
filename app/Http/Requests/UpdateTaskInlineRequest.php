<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Support\RoleAccess;
use App\Support\TaskViewAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskInlineRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $task = $this->route('task');

        if ($user === null || ! $task instanceof Task) {
            return false;
        }

        $field = (string) $this->input('field');

        if ($field === 'responsible_id') {
            return RoleAccess::canMutateTask($user, $task)
                || RoleAccess::canBulkMutateTasks($user);
        }

        return RoleAccess::canMutateTask($user, $task);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'field' => ['required', 'string', Rule::in(['priority', 'responsible_id'])],
            'value' => [
                'required',
                Rule::when(
                    $this->input('field') === 'priority',
                    [Rule::in(['low', 'medium', 'high', 'critical'])],
                ),
                Rule::when(
                    $this->input('field') === 'responsible_id',
                    ['integer', 'exists:users,id'],
                ),
            ],
        ];
    }

    protected function passedValidation(): void
    {
        $user = $this->user();

        if ($user === null || $user->isAdmin() || $this->input('field') !== 'responsible_id') {
            return;
        }

        if (! TaskViewAuthorization::userCanAssignToUser($user, (int) $this->input('value'))) {
            abort(403);
        }
    }
}
