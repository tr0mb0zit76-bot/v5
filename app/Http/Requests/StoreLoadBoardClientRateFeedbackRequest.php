<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\CurrencyDictionary;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoadBoardClientRateFeedbackRequest extends FormRequest
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
            'rate' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3', Rule::in(CurrencyDictionary::allowedCodes())],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.required' => 'Укажите ставку, по которой клиент возит.',
            'rate.min' => 'Ставка клиента должна быть больше нуля.',
        ];
    }
}
