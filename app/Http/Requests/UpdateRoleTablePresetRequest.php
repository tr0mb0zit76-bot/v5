<?php

namespace App\Http\Requests;

use App\Support\OrderTableColumns;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleTablePresetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return [
            'orders' => ['required', 'array', 'min:1'],
            'orders.*.colId' => ['required', 'string', Rule::in(OrderTableColumns::fields())],
            'orders.*.hide' => ['required', 'boolean'],
            'orders.*.width' => ['required', 'integer', 'min:60', 'max:500'],
            'orders.*.order' => ['required', 'integer', 'min:0'],
        ];
    }
}
