<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOwnFleetCostNormsRequest extends FormRequest
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
            'cn.fuel_price_rub_per_liter' => ['required', 'numeric', 'min:0'],
            'cn.fuel_consumption_l_per_100km' => ['required', 'numeric', 'min:0'],
            'cn.driver_rub_per_km' => ['required', 'numeric', 'min:0'],
            'cn.other_rub_per_km' => ['required', 'numeric', 'min:0'],
            'ru.fuel_price_rub_per_liter' => ['required', 'numeric', 'min:0'],
            'ru.fuel_consumption_l_per_100km' => ['required', 'numeric', 'min:0'],
            'ru.driver_rub_per_km' => ['required', 'numeric', 'min:0'],
            'ru.other_rub_per_km' => ['required', 'numeric', 'min:0'],
            'depreciation_rub_per_km' => ['required', 'numeric', 'min:0'],
            'margin_percent' => ['required', 'numeric', 'min:0'],
            'margin_absolute_rub' => ['required', 'numeric', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cn.fuel_price_rub_per_liter' => 'цена топлива (Китай), ₽/л',
            'cn.fuel_consumption_l_per_100km' => 'расход топлива (Китай), л/100 км',
            'cn.driver_rub_per_km' => 'труд водителя (Китай), ₽/км',
            'cn.other_rub_per_km' => 'прочее (Китай), ₽/км',
            'ru.fuel_price_rub_per_liter' => 'цена топлива (РФ), ₽/л',
            'ru.fuel_consumption_l_per_100km' => 'расход топлива (РФ), л/100 км',
            'ru.driver_rub_per_km' => 'труд водителя (РФ), ₽/км',
            'ru.other_rub_per_km' => 'прочее (РФ), ₽/км',
            'depreciation_rub_per_km' => 'амортизация, ₽/км',
            'margin_percent' => 'наценка, %',
            'margin_absolute_rub' => 'надбавка, ₽',
        ];
    }
}
