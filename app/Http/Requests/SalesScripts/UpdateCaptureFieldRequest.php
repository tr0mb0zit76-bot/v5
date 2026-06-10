<?php

namespace App\Http\Requests\SalesScripts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCaptureFieldRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:120'],
            'value_type' => ['nullable', 'string', 'max:32', Rule::in(['text'])],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
