<?php

namespace App\Http\Requests;

use App\Support\GridViewCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGridViewRequest extends FormRequest
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
            'grid_key' => ['required', 'string', Rule::in(GridViewCatalog::gridKeys())],
            'name' => ['required', 'string', 'max:120'],
            'visibility' => ['nullable', 'string', Rule::in(GridViewCatalog::visibilityOptions())],
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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'grid_key.required' => 'Укажите грид.',
            'grid_key.in' => 'Неизвестный грид.',
            'name.required' => 'Укажите название представления.',
        ];
    }
}
