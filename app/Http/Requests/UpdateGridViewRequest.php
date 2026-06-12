<?php

namespace App\Http\Requests;

use App\Support\GridViewCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGridViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'visibility' => ['sometimes', 'required', 'string', Rule::in(GridViewCatalog::visibilityOptions())],
            'shared_with' => ['nullable', 'array'],
            'shared_with.role_ids' => ['nullable', 'array'],
            'shared_with.role_ids.*' => ['integer', 'min:1'],
            'shared_with.user_ids' => ['nullable', 'array'],
            'shared_with.user_ids.*' => ['integer', 'min:1'],
            'column_state' => ['nullable', 'array'],
            'filter_state' => ['nullable', 'array'],
            'sort_state' => ['nullable', 'array'],
            'quick_search' => ['nullable', 'string', 'max:500'],
            'is_pinned_sidebar' => ['nullable', 'boolean'],
        ];
    }
}
