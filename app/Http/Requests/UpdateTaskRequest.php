<?php

namespace App\Http\Requests;

use App\Models\Task;
use App\Support\RoleAccess;
use App\Support\TaskStatus;
use App\Support\TaskViewAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
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

        $contractorId = $this->input('contractor_id');
        if ($contractorId === '' || $contractorId === null) {
            $this->merge(['contractor_id' => null]);
        }
    }

    /**
     * Determine if the user is authorized to make this request.
     */
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
            'status' => ['required', 'string', Rule::in(TaskStatus::values())],
            'due_at' => ['nullable', 'date'],
            'sla_deadline_at' => ['nullable', 'date'],
            'responsible_id' => ['required', 'integer', 'exists:users,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'order_id' => ['nullable', 'integer'],
            'contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
        ];
    }

    protected function passedValidation(): void
    {
        $user = $this->user();

        if ($user === null || $user->isAdmin()) {
            return;
        }

        if (! TaskViewAuthorization::userCanAssignToUser($user, (int) $this->input('responsible_id'))) {
            abort(403);
        }
    }
}
