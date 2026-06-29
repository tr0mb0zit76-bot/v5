<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MailThreadAnalysisFeedbackRequest extends FormRequest
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
            'suggestion_key' => ['required', 'uuid'],
            'rating' => ['required', 'string', Rule::in(['helpful', 'not_helpful'])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
