<?php

namespace App\Http\Requests;

use App\Support\ContractorTableColumns;
use App\Support\LeadTableColumns;
use App\Support\OrderTableColumns;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRoleTablePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canAccessSettingsSystem($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'table' => ['required', 'string', 'in:orders,leads,contractors'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*.colId' => ['required', 'string'],
            'columns.*.hide' => ['required', 'boolean'],
            'columns.*.width' => ['required', 'integer', 'min:60', 'max:500'],
            'columns.*.order' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $table = $this->string('table')->toString();
            $allowedFields = match ($table) {
                'orders' => OrderTableColumns::fields(),
                'leads' => LeadTableColumns::fields(),
                'contractors' => ContractorTableColumns::fields(),
                default => [],
            };

            foreach ((array) $this->input('columns', []) as $index => $column) {
                if (! in_array($column['colId'] ?? null, $allowedFields, true)) {
                    $validator->errors()->add("columns.{$index}.colId", 'Недопустимая колонка для выбранной таблицы.');
                }
            }
        });
    }
}
