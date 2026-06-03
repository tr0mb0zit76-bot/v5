<?php

namespace App\Http\Requests;

use App\Support\RoleAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesBookQuizAttemptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleAccess::canReadSalesBook($this->user());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'min:1'],
            'answers.*' => ['required', 'string', 'max:32'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'Ответы на тест не переданы.',
            'answers.array' => 'Ответы должны быть переданы списком.',
            'answers.min' => 'Ответы на тест не переданы.',
        ];
    }
}
