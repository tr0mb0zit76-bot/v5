<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreSalesBookPageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Временное решение, потом добавить политики
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $pageId = $this->route('page') ? $this->route('page')->id : null;
        $bookId = $this->route('book') ? $this->route('book')->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:sales_book_pages,slug,'.$pageId.',id,sales_book_id,'.$bookId],
            'content' => ['nullable', 'array'],
            'raw_markdown' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'parent_id' => ['nullable', 'exists:sales_book_pages,id'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'depth' => ['nullable', 'integer', 'min:0'],
            'status' => ['required', 'in:draft,published,archived'],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Генерируем slug из title, если не указан
        if (! $this->has('slug') && $this->has('title')) {
            $this->merge([
                'slug' => Str::slug($this->title),
            ]);
        }

        // Устанавливаем author_id текущего пользователя
        $this->merge([
            'author_id' => auth()->id(),
        ]);

        // Устанавливаем sales_book_id из маршрута
        if ($this->route('book')) {
            $this->merge([
                'sales_book_id' => $this->route('book')->id,
            ]);
        }

        // Устанавливаем depth на основе parent_id
        if ($this->has('parent_id')) {
            $depth = $this->parent_id ? 1 : 0; // Базовый depth, можно улучшить
            $this->merge([
                'depth' => $depth,
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Название страницы обязательно для заполнения',
            'title.max' => 'Название страницы не должно превышать 255 символов',
            'slug.required' => 'Slug обязателен для заполнения',
            'slug.unique' => 'Такой slug уже используется в этой книге',
            'status.required' => 'Статус обязателен для выбора',
            'status.in' => 'Выбран недопустимый статус',
        ];
    }
}
