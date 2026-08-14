<?php

namespace App\Http\Requests;

use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BatchAllocateManagementStatementLinesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canManageStatementImport($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.line_id' => ['required', 'integer', 'distinct', 'exists:management_statement_lines,id'],
            'items.*.allocation_type' => ['required', Rule::in(['operational', 'payroll', 'category'])],
            'items.*.category_id' => ['nullable', 'integer', 'exists:management_expense_categories,id'],
            'items.*.payment_schedule_id' => ['nullable', 'integer', 'exists:payment_schedules,id'],
            'items.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'items.*.amount' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
            'items.*.allocations' => ['nullable', 'array', 'min:2'],
            'items.*.allocations.*.payment_schedule_id' => ['required_with:items.*.allocations', 'integer', 'exists:payment_schedules,id'],
            'items.*.allocations.*.amount' => ['required_with:items.*.allocations', 'numeric', 'min:0.01'],
            'items.*.remember_keyword' => ['nullable', 'string', 'max:120'],
            'items.*.remember_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $import = $this->route('import');
                if (! $import instanceof ManagementStatementImport) {
                    return;
                }

                $lineIds = collect($this->input('items', []))
                    ->pluck('line_id')
                    ->filter()
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values();

                $foreignCount = ManagementStatementLine::query()
                    ->whereIn('id', $lineIds)
                    ->where(function ($query) use ($import): void {
                        $query->whereNull('import_id')
                            ->orWhere('import_id', '!=', $import->id);
                    })
                    ->count();

                if ($foreignCount > 0) {
                    $validator->errors()->add('items', 'Можно разносить только строки этой выписки.');
                }
            },
        ];
    }
}
