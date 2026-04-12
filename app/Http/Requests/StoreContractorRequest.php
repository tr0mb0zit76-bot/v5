<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('owner_id') && $this->input('owner_id') === '') {
            $this->merge(['owner_id' => null]);
        }

        foreach (['inn', 'kpp', 'ogrn', 'okpo', 'bik', 'account_number'] as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if ($value === null || $value === '') {
                continue;
            }

            if (! is_string($value)) {
                $this->merge([$key => (string) $value]);
            }
        }

        $this->truncateStringFields([
            'name' => 255,
            'full_name' => 255,
            'inn' => 20,
            'kpp' => 20,
            'ogrn' => 20,
            'okpo' => 20,
            'legal_address' => 255,
            'actual_address' => 255,
            'postal_address' => 255,
        ]);

        if ($this->has('contacts') && is_array($this->input('contacts'))) {
            $contacts = collect($this->input('contacts'))
                ->filter(function (mixed $row): bool {
                    if (! is_array($row)) {
                        return false;
                    }

                    return filled(trim((string) ($row['full_name'] ?? '')));
                })
                ->values()
                ->all();
            $this->merge(['contacts' => $contacts]);
        }

        if ($this->has('documents') && is_array($this->input('documents'))) {
            $documents = collect($this->input('documents'))
                ->filter(function (mixed $row): bool {
                    if (! is_array($row)) {
                        return false;
                    }

                    return filled(trim((string) ($row['title'] ?? '')));
                })
                ->map(function (mixed $row): mixed {
                    if (! is_array($row)) {
                        return $row;
                    }

                    if (($row['document_date'] ?? '') === '') {
                        $row['document_date'] = null;
                    }

                    return $row;
                })
                ->values()
                ->all();
            $this->merge(['documents' => $documents]);
        }

        if ($this->has('interactions') && is_array($this->input('interactions'))) {
            $interactions = collect($this->input('interactions'))
                ->map(function (mixed $row): mixed {
                    if (! is_array($row)) {
                        return $row;
                    }

                    if (($row['contacted_at'] ?? '') === '') {
                        $row['contacted_at'] = null;
                    }

                    return $row;
                })
                ->values()
                ->all();
            $this->merge(['interactions' => $interactions]);
        }

        foreach (['default_customer_payment_schedule', 'default_carrier_payment_schedule'] as $scheduleKey) {
            if (! $this->has($scheduleKey) || ! is_array($this->input($scheduleKey))) {
                continue;
            }

            $schedule = $this->input($scheduleKey);
            $postpaymentMode = mb_strtolower((string) ($schedule['postpayment_mode'] ?? ''));
            $isEventPaymentMode = in_array($postpaymentMode, ['loading', 'unloading'], true);
            $hasPrepayment = ! $isEventPaymentMode
                && filter_var($schedule['has_prepayment'] ?? false, FILTER_VALIDATE_BOOLEAN);

            foreach (['prepayment_ratio', 'prepayment_days', 'postpayment_days'] as $numericKey) {
                if (($schedule[$numericKey] ?? null) === '') {
                    $schedule[$numericKey] = null;
                }
            }

            if ($isEventPaymentMode) {
                $schedule['postpayment_days'] = 0;
            }

            if (! $hasPrepayment) {
                $schedule['prepayment_ratio'] = null;
                $schedule['prepayment_days'] = null;
                $schedule['prepayment_mode'] = null;
            }

            $this->merge([$scheduleKey => $schedule]);
        }
    }

    /**
     * @param  array<string, int>  $fields
     */
    private function truncateStringFields(array $fields): void
    {
        foreach ($fields as $field => $maxLength) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if ($value === null || $value === '') {
                continue;
            }

            $stringValue = trim((string) $value);

            if (mb_strlen($stringValue) <= $maxLength) {
                $this->merge([$field => $stringValue]);

                continue;
            }

            $this->merge([$field => mb_substr($stringValue, 0, $maxLength)]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    public function rules(): array
    {
        return $this->baseRules();
    }

    /**
     * @return array<string, ValidationRule|array<int, ValidationRule|string>|string>
     */
    protected function baseRules(): array
    {
        return [
            'type' => ['required', Rule::in(['customer', 'carrier', 'both'])],
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'inn' => ['nullable', 'string', 'max:20'],
            'kpp' => ['nullable', 'string', 'max:20'],
            'ogrn' => ['nullable', 'string', 'max:20'],
            'okpo' => ['nullable', 'string', 'max:20'],
            'legal_form' => ['nullable', Rule::in(['ooo', 'zao', 'ao', 'ip', 'samozanyaty', 'other'])],
            'legal_address' => ['nullable', 'string', 'max:255'],
            'actual_address' => ['nullable', 'string', 'max:255'],
            'postal_address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_person_phone' => ['nullable', 'string', 'max:50'],
            'contact_person_email' => ['nullable', 'string', 'max:255'],
            'contact_person_position' => ['nullable', 'string', 'max:255'],
            'signer_name_nominative' => ['nullable', 'string', 'max:255'],
            'signer_name_prepositional' => ['nullable', 'string', 'max:255'],
            'signer_authority_basis' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bik' => ['nullable', 'string', 'max:9'],
            'account_number' => ['nullable', 'string', 'max:20'],
            'correspondent_account' => ['nullable', 'string', 'max:20'],
            'ati_id' => ['nullable', 'string', 'max:50'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:255'],
            'activity_types' => ['nullable', 'array'],
            'activity_types.*' => ['string', 'max:255'],
            'transport_requirements' => ['nullable', 'array'],
            'transport_requirements.*' => ['string', 'max:255'],
            'debt_limit' => ['nullable', 'numeric', 'min:0'],
            'debt_limit_currency' => ['nullable', Rule::in(['RUB', 'USD', 'CNY', 'EUR'])],
            'stop_on_limit' => ['required', 'boolean'],
            'default_customer_payment_form' => ['nullable', Rule::in(['vat', 'no_vat', 'cash'])],
            'default_customer_payment_term' => ['nullable', 'string', 'max:255'],
            'default_customer_payment_schedule' => ['nullable', 'array'],
            'default_customer_payment_schedule.has_prepayment' => ['nullable', 'boolean'],
            'default_customer_payment_schedule.prepayment_ratio' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'default_customer_payment_schedule.prepayment_days' => ['nullable', 'integer', 'min:0'],
            'default_customer_payment_schedule.prepayment_mode' => ['nullable', Rule::in(['fttn', 'ottn', 'loading', 'unloading'])],
            'default_customer_payment_schedule.postpayment_days' => ['nullable', 'integer', 'min:0'],
            'default_customer_payment_schedule.postpayment_mode' => ['nullable', Rule::in(['fttn', 'ottn', 'loading', 'unloading'])],
            'default_carrier_payment_form' => ['nullable', Rule::in(['vat', 'no_vat', 'cash'])],
            'default_carrier_payment_term' => ['nullable', 'string', 'max:255'],
            'default_carrier_payment_schedule' => ['nullable', 'array'],
            'default_carrier_payment_schedule.has_prepayment' => ['nullable', 'boolean'],
            'default_carrier_payment_schedule.prepayment_ratio' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'default_carrier_payment_schedule.prepayment_days' => ['nullable', 'integer', 'min:0'],
            'default_carrier_payment_schedule.prepayment_mode' => ['nullable', Rule::in(['fttn', 'ottn', 'loading', 'unloading'])],
            'default_carrier_payment_schedule.postpayment_days' => ['nullable', 'integer', 'min:0'],
            'default_carrier_payment_schedule.postpayment_mode' => ['nullable', Rule::in(['fttn', 'ottn', 'loading', 'unloading'])],
            'cooperation_terms_notes' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'is_verified' => ['required', 'boolean'],
            'is_own_company' => ['required', 'boolean'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.full_name' => ['required', 'string', 'max:255'],
            'contacts.*.position' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.email' => ['nullable', 'string', 'max:255'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
            'contacts.*.notes' => ['nullable', 'string'],
            'interactions' => ['nullable', 'array'],
            'interactions.*.contacted_at' => ['nullable', 'date'],
            'interactions.*.channel' => ['nullable', 'string', 'max:50'],
            'interactions.*.subject' => ['nullable', 'string', 'max:255'],
            'interactions.*.summary' => ['nullable', 'string'],
            'interactions.*.result' => ['nullable', 'string', 'max:255'],
            'documents' => ['nullable', 'array'],
            'documents.*.type' => ['nullable', 'string', 'max:255'],
            'documents.*.title' => ['required', 'string', 'max:255'],
            'documents.*.number' => ['nullable', 'string', 'max:255'],
            'documents.*.document_date' => ['nullable', 'date'],
            'documents.*.status' => ['nullable', 'string', 'max:255'],
            'documents.*.notes' => ['nullable', 'string'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
