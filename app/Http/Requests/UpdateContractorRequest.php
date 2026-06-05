<?php

namespace App\Http\Requests;

use App\Models\Contractor;
use App\Models\User;
use App\Support\ContractorDuplicateGuard;
use App\Support\ContractorWorkStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class UpdateContractorRequest extends StoreContractorRequest
{
    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        /** @var Contractor $contractor */
        $contractor = $this->route('contractor');

        $rules['name'] = [
            'required',
            'string',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail) use ($contractor): void {
                ContractorDuplicateGuard::failIfNameTaken($value, $this->user(), $contractor->id, $fail);
            },
        ];

        $rules['inn'] = [
            'nullable',
            'string',
            'max:20',
            function (string $attribute, mixed $value, \Closure $fail) use ($contractor): void {
                ContractorDuplicateGuard::failIfInnTaken($value, $this->user(), $contractor->id, $fail);
            },
        ];

        $allowedWorkStatuses = ContractorWorkStatus::manualValues();
        if ($contractor->work_status === ContractorWorkStatus::WORK_PAUSE) {
            $allowedWorkStatuses[] = ContractorWorkStatus::WORK_PAUSE;
        }

        $rules['work_status'] = ['nullable', 'string', Rule::in($allowedWorkStatuses)];

        if (! Schema::hasColumn('users', 'is_active')) {
            return $rules;
        }

        $rules['owner_id'] = [
            'nullable',
            'integer',
            function (string $attribute, mixed $value, \Closure $fail) use ($contractor): void {
                if ($value === null || $value === '') {
                    return;
                }

                $id = (int) $value;
                $user = User::query()->find($id);

                if ($user === null) {
                    $fail(__('validation.exists', ['attribute' => $attribute]));

                    return;
                }

                if ($user->is_active) {
                    return;
                }

                if ((int) ($contractor->owner_id ?? 0) === $id) {
                    return;
                }

                $fail('Назначить владельцем можно только активного пользователя.');
            },
        ];

        return $rules;
    }
}
