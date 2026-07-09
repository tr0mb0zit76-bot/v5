<?php

namespace App\Http\Requests;

use App\Models\CompanyInitiative;
use App\Models\CompanyInitiativeMilestone;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ReorderCompanyInitiativeMilestonesRequest extends FormRequest
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
        return [
            'milestone_ids' => ['required', 'array', 'min:1'],
            'milestone_ids.*' => ['required', 'integer', 'distinct'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var CompanyInitiative|null $initiative */
            $initiative = $this->route('initiative');
            if ($initiative === null) {
                return;
            }

            $requestedIds = collect($this->input('milestone_ids', []))
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            $existingIds = CompanyInitiativeMilestone::query()
                ->where('company_initiative_id', $initiative->id)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->sort()
                ->values()
                ->all();

            if ($requestedIds !== $existingIds) {
                $validator->errors()->add(
                    'milestone_ids',
                    'Передайте полный список этапов инициативы в нужном порядке.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'milestone_ids.required' => 'Укажите порядок этапов.',
            'milestone_ids.*.distinct' => 'Этапы не должны повторяться.',
        ];
    }
}
