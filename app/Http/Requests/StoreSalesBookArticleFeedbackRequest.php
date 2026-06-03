<?php

namespace App\Http\Requests;

use App\Enums\SalesBookArticleFeedbackRating;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesBookArticleFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'string', Rule::enum(SalesBookArticleFeedbackRating::class)],
            'comment' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.required' => 'Выберите оценку статьи.',
            'rating.enum' => 'Недопустимая оценка.',
            'comment.max' => 'Комментарий слишком длинный (не более 2000 символов).',
        ];
    }
}
