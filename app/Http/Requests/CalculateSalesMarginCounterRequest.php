<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateSalesMarginCounterRequest extends FormRequest
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
            'customer_without_vat' => ['nullable', 'numeric', 'min:0'],
            'customer_with_vat' => ['nullable', 'numeric', 'min:0'],
            'carrier_without_vat' => ['nullable', 'numeric', 'min:0'],
            'carrier_with_vat' => ['nullable', 'numeric', 'min:0'],
            'additional_expenses' => ['nullable', 'numeric', 'min:0'],
            'insurance' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'order_date' => ['nullable', 'date'],
        ];
    }
}
