<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

        if (in_array($field, ['customer_payment_form', 'carrier_payment_form', 'manual_status'], true)) {
            return blank($value) ? null : (string) $value;
        }

        return blank($value) ? null : trim((string) $value);
    }
}
