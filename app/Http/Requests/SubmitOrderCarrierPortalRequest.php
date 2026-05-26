<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitOrderCarrierPortalRequest extends FormRequest
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
            'tractor_plate' => ['nullable', 'string', 'max:32'],
            'trailer_plate' => ['nullable', 'string', 'max:32'],
            'tractor_brand' => ['nullable', 'string', 'max:120'],
            'trailer_brand' => ['nullable', 'string', 'max:120'],
            'driver_full_name' => ['required', 'string', 'max:255'],
            'driver_phone' => ['nullable', 'string', 'max:32'],
            'driver_license' => ['nullable', 'string', 'max:64'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'driver_full_name.required' => 'Укажите ФИО водителя.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $tractor = trim((string) $this->input('tractor_plate', ''));
            $trailer = trim((string) $this->input('trailer_plate', ''));

            if ($tractor === '' && $trailer === '') {
                $validator->errors()->add('tractor_plate', 'Укажите госномер тягача или прицепа.');
            }
        });
    }
}
