<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CommandBarAgentFeedbackRequest extends FormRequest
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
            'turn_id' => ['required', 'uuid'],
            'rating' => ['required', 'string', Rule::in(['helpful', 'not_helpful'])],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'turn_id.required' => 'Укажите идентификатор ответа.',
            'turn_id.uuid' => 'Некорректный идентификатор ответа.',
            'rating.required' => 'Укажите оценку ответа.',
            'rating.in' => 'Оценка должна быть helpful или not_helpful.',
        ];
    }
}
