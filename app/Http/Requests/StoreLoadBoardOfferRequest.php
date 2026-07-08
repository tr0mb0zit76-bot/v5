<?php

namespace App\Http\Requests;

use App\Support\LoadBoardOfferSource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoadBoardOfferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'carrier_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'carrier_rate' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'carrier_rate_currency' => ['nullable', 'string', 'size:3'],
            'payment_form' => ['nullable', 'string', 'max:255'],
            'available_date' => ['nullable', 'date'],
            'carrier_contact' => ['nullable', 'string', 'max:255'],
            'conditions' => ['nullable', 'string', 'max:5000'],
            'comment' => ['nullable', 'string', 'max:5000'],
            'source' => ['nullable', 'string', 'in:'.implode(',', LoadBoardOfferSource::values())],
        ];
    }
}
