<?php

namespace App\Http\Requests\Mobile;

use App\Services\Mobile\MobileDeviceService;
use Illuminate\Foundation\Http\FormRequest;

class RegisterMobileDeviceRequest extends FormRequest
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
            'device_key' => ['required', 'string', 'size:36'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'pin' => [
                'required',
                'string',
                'regex:/^\d{'.MobileDeviceService::PIN_MIN_LENGTH.','.MobileDeviceService::PIN_MAX_LENGTH.'}$/',
                'confirmed',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pin.regex' => 'PIN должен содержать от '.MobileDeviceService::PIN_MIN_LENGTH.' до '.MobileDeviceService::PIN_MAX_LENGTH.' цифр.',
            'pin.confirmed' => 'PIN и подтверждение не совпадают.',
        ];
    }
}
