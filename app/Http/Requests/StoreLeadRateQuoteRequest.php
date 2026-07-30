<?php

namespace App\Http\Requests;

use App\Models\Lead;
use App\Models\LeadRateQuote;
use App\Support\LeadViewAuthorization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRateQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $lead = $this->route('lead');

        return $lead instanceof Lead
            && LeadViewAuthorization::userCanViewLead($this->user(), $lead);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'contractor_id' => ['nullable', 'integer', 'exists:contractors,id'],
            'carrier_name' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_form' => ['nullable', 'string', 'max:255'],
            'valid_until' => ['nullable', 'date'],
            'source' => [
                'nullable',
                'string',
                Rule::in([
                    LeadRateQuote::SOURCE_MANUAL,
                    LeadRateQuote::SOURCE_PHONE,
                    LeadRateQuote::SOURCE_ATI,
                    LeadRateQuote::SOURCE_LOAD_BOARD,
                    LeadRateQuote::SOURCE_OTHER,
                ]),
            ],
            'comment' => ['nullable', 'string', 'max:5000'],
            'load_board_offer_id' => ['nullable', 'integer', 'exists:load_board_offers,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.required' => 'Укажите ставку перевозчика.',
        ];
    }
}
