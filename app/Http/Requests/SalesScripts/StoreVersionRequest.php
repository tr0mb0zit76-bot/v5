<?php

namespace App\Http\Requests\SalesScripts;

use App\Models\SalesScriptVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'duplicate_from_version_id' => ['nullable', 'integer', 'exists:sales_script_versions,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('duplicate_from_version_id')) {
                return;
            }

            /** @var SalesScript|null $script */
            $script = $this->route('script');
            if ($script === null) {
                return;
            }

            $belongs = SalesScriptVersion::query()
                ->whereKey($this->integer('duplicate_from_version_id'))
                ->where('sales_script_id', $script->id)
                ->exists();

            if (! $belongs) {
                $validator->errors()->add('duplicate_from_version_id', 'Версия для копирования должна принадлежать этому сценарию.');
            }
        });
    }
}
