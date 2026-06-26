<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderTrackReceivedRequest extends FormRequest
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
            'field' => ['required', Rule::in(['track_received_date_customer', 'track_received_date_carrier'])],
            'value' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'field.required' => 'Укажите поле для обновления.',
            'field.in' => 'Недопустимое поле даты получения.',
            'value.date' => 'Укажите корректную дату.',
        ];
    }
}
