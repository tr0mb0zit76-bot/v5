<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderCarrierPortalInviteRequest extends FormRequest
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
            'contractor_id' => ['required', 'integer', 'exists:contractors,id'],
            'stage' => ['required', 'string', 'max:64'],
            'carrier_slot' => ['nullable', 'integer', 'min:1', 'max:4'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contractor_id.required' => 'Укажите перевозчика.',
            'contractor_id.exists' => 'Перевозчик не найден.',
            'stage.required' => 'Укажите плечо заказа.',
        ];
    }

    public function carrierSlot(): int
    {
        $slot = $this->integer('carrier_slot');

        return $slot > 0 ? $slot : 1;
    }
}
