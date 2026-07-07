<?php

namespace App\Http\Requests;

use App\Support\CompanyPlanningCatalog;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCompanyInitiativeDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessCompanyPlanning($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $initiative = $this->route('initiative');
        $initiativeId = $initiative?->id;

        return [
            'blocked_milestone_id' => [
                'required',
                'integer',
                Rule::exists('company_initiative_milestones', 'id')->where(function ($query) use ($initiativeId): void {
                    $query->where('company_initiative_id', $initiativeId);
                }),
            ],
            'depends_on_milestone_id' => [
                'required',
                'integer',
                'different:blocked_milestone_id',
                Rule::exists('company_initiative_milestones', 'id')->where(function ($query) use ($initiativeId): void {
                    $query->where('company_initiative_id', $initiativeId);
                }),
            ],
            'type' => ['nullable', 'string', Rule::in(CompanyPlanningCatalog::DEPENDENCY_TYPES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $blockedId = (int) $this->input('blocked_milestone_id');
            $dependsOnId = (int) $this->input('depends_on_milestone_id');

            if ($blockedId <= 0 || $dependsOnId <= 0 || $blockedId === $dependsOnId) {
                return;
            }

            $initiative = $this->route('initiative');
            if ($initiative === null) {
                return;
            }

            $exists = $initiative->dependencies()
                ->where('blocked_milestone_id', $blockedId)
                ->where('depends_on_milestone_id', $dependsOnId)
                ->exists();

            if ($exists) {
                $validator->errors()->add('depends_on_milestone_id', 'Такая зависимость уже добавлена.');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'blocked_milestone_id.required' => 'Выберите этап, который зависит от другого.',
            'depends_on_milestone_id.required' => 'Выберите предшествующий этап.',
            'depends_on_milestone_id.different' => 'Этап не может зависеть от самого себя.',
        ];
    }
}
