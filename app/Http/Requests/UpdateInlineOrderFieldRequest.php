<?php

namespace App\Http\Requests;

use App\Support\PaymentFormDictionary;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateInlineOrderFieldRequest extends FormRequest
{
    /**
     * @var list<string>
     */
    private const ALLOWED_FIELDS = [
        'customer_rate',
        'carrier_rate',
        'additional_expenses',
        'insurance',
        'bonus',
        'invoice_number',
        'upd_number',
        'waybill_number',
        'track_number_customer',
        'track_sent_date_customer',
        'track_received_date_customer',
        'track_number_carrier',
        'track_sent_date_carrier',
        'track_received_date_carrier',
        'customer_payment_form',
        'carrier_payment_form',
        'manual_status',
        'order_date',
    ];

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
            'field' => ['required', 'string', Rule::in(self::ALLOWED_FIELDS)],
            'value' => ['nullable'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            $field = $this->input('field');
            if (! in_array($field, ['customer_payment_form', 'carrier_payment_form'], true)) {
                return;
            }

            $value = $this->input('value');
            if ($value === null || $value === '' || $value === 'null') {
                return;
            }

            $codes = PaymentFormDictionary::allowedCodesForValidation();
            if (! in_array((string) $value, $codes, true)) {
                $validator->errors()->add('value', 'Недопустимая форма оплаты.');
            }
        });
    }

    /**
     * @return array{field: string, value: mixed}
     */
    public function validatedPayload(): array
    {
        $validated = $this->validated();
        $field = $validated['field'];

        return [
            'field' => $field,
            'value' => $this->normalizeValue($field, $validated['value'] ?? null),
        ];
    }

    private function normalizeValue(string $field, mixed $value): mixed
    {
        if ($value === '' || $value === 'null') {
            return null;
        }

        if (in_array($field, ['customer_rate', 'carrier_rate', 'additional_expenses', 'insurance', 'bonus'], true)) {
            return $value === null ? null : round((float) $value, 2);
        }

        if (in_array($field, [
            'track_sent_date_customer',
            'track_received_date_customer',
            'track_sent_date_carrier',
            'track_received_date_carrier',
            'order_date',
        ], true)) {
            return blank($value) ? null : $value;
        }

        if (in_array($field, ['customer_payment_form', 'carrier_payment_form'], true)) {
            if (blank($value)) {
                return null;
            }

            return PaymentFormDictionary::normalizeForStorage((string) $value) ?? (string) $value;
        }

        if ($field === 'manual_status') {
            return blank($value) ? null : (string) $value;
        }

        return blank($value) ? null : trim((string) $value);
    }
}
