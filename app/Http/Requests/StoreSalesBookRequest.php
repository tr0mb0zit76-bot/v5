<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreSalesBookRequest extends FormRequest
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
        $bookId = $this->route('book') ? $this->route('book')->id : null;

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:sales_books,slug,'.$bookId],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover_image' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:draft,published,archived'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:sales_book_categories,id'],
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
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Название книги обязательно для заполнения',
            'title.max' => 'Название книги не должно превышать 255 символов',
            'slug.required' => 'Slug обязателен для заполнения',
            'slug.unique' => 'Такой slug уже используется',
            'status.required' => 'Статус обязателен для выбора',
            'status.in' => 'Выбран недопустимый статус',
        ];
    }
}
