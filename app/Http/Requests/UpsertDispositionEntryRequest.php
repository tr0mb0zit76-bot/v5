<?php

namespace App\Http\Requests;

use App\Support\DispositionSlot;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertDispositionEntryRequest extends FormRequest
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
            'order_id' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
            'slot' => ['required', 'string', Rule::in(DispositionSlot::values())],
            'location' => ['nullable', 'string', 'max:500'],
            'comment' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'Укажите заказ.',
            'date.required' => 'Укажите дату.',
            'slot.required' => 'Укажите слот (утро или вечер).',
            'slot.in' => 'Слот должен быть morning или evening.',
        ];
    }
}
