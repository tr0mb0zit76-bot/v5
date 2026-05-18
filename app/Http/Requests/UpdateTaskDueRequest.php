<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskDueRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $task = $this->route('task');

        if ($user === null || ! $task instanceof Task) {
            return false;
        }

        return RoleAccess::canMutateTask($user, $task);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'due_at' => ['required', 'date'],
        ];
    }
}
