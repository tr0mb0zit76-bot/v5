<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateHowMuchCostsRequest extends FormRequest
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
            'km_to_border' => ['required', 'numeric', 'min:0'],
            'km_from_border' => ['required', 'numeric', 'min:0'],
            'margin_percent' => ['nullable', 'numeric', 'min:0'],
            'margin_absolute_rub' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'km_to_border' => 'км до границы',
            'km_from_border' => 'км от границы',
            'margin_percent' => 'наценка, %',
            'margin_absolute_rub' => 'надбавка, ₽',
        ];
    }
}
