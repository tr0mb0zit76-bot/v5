<?php

namespace App\Http\Requests;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Schema;

class UpdateContractorRequest extends StoreContractorRequest
{
    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        if (! Schema::hasColumn('users', 'is_active')) {
            return $rules;
        }

        /** @var Contractor $contractor */
        $contractor = $this->route('contractor');

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
