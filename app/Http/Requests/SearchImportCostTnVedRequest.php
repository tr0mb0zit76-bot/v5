<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchImportCostTnVedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:120'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => 'Введите код или название для поиска.',
            'q.min' => 'Введите не менее 2 символов для поиска.',
        ];
    }
}
