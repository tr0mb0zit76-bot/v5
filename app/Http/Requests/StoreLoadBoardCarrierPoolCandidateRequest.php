<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\LoadBoardOfferSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLoadBoardCarrierPoolCandidateRequest extends FormRequest
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
            'carrier_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'carrier_name' => ['nullable', 'string', 'max:255'],
            'carrier_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'carrier_rate_currency' => ['nullable', 'string', 'size:3'],
            'carrier_contact' => ['nullable', 'string', 'max:255'],
            'conditions' => ['nullable', 'string', 'max:5000'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'source' => ['required', 'string', 'in:'.implode(',', LoadBoardOfferSource::values())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $carrierId = $this->input('carrier_id');
            $carrierName = trim((string) $this->input('carrier_name', ''));
            $carrierContact = trim((string) $this->input('carrier_contact', ''));

            if (($carrierId === null || $carrierId === '') && $carrierName === '' && $carrierContact === '') {
                $validator->errors()->add(
                    'carrier_id',
                    'Укажите перевозчика из справочника, название или контакт.',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source.required' => 'Укажите источник кандидата.',
            'source.in' => 'Недопустимый источник кандидата.',
        ];
    }
}
