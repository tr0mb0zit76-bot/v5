<?php

namespace App\Http\Requests;

use App\Rules\DocumentWithinPageBudget;
use App\Support\ContractorDuplicateGuard;
use App\Support\ContractorIdentity;
use App\Support\ContractorWorkStatus;
use App\Support\CurrencyDictionary;
use App\Support\DocumentUploadBudget;
use App\Support\EdoProviderDictionary;
use App\Support\MailSync\PublicMailDomainCatalog;
use App\Support\PartyNormsPenalties;
use App\Support\PaymentFormDictionary;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

class StoreContractorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('contractor_payload')) {
            try {
                /** @var array<string, mixed> $data */
                $data = json_decode($this->string('contractor_payload')->value(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ValidationException::withMessages([
                    'contractor_payload' => 'Некорректный JSON контрагента.',
                ]);
            }

            if (! is_array($data)) {
                throw ValidationException::withMessages([
                    'contractor_payload' => 'Некорректный JSON контрагента.',
                ]);
            }

            $documents = $data['documents'] ?? [];
            if (is_array($documents)) {
                foreach (array_keys($documents) as $index) {
                    $uploadKey = 'contractor_document_file_'.$index;
                    if ($this->hasFile($uploadKey)) {
                        $documents[$index]['file'] = $this->file($uploadKey);
                    }
                }
                $data['documents'] = $documents;
            }

            $this->merge($this->normalizeEmptyStringsToNull($data));
        }

        if ($this->has('owner_id') && $this->input('owner_id') === '') {
            $this->merge(['owner_id' => null]);
        }

        if ($this->routeIs('contractors.store') && ! $this->filled('owner_id') && $this->user() !== null) {
            $this->merge(['owner_id' => $this->user()->id]);
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

        foreach (['non_resident_corr_bank_name', 'non_resident_corr_bank_swift', 'non_resident_corr_settlement_account', 'non_resident_corr_bank_account', 'cnaps_code'] as $key) {
            if (! $this->has($key)) {
                continue;
            }

            $value = $this->input($key);

            if ($value === null || $value === '') {
                continue;
            }

            if ($key === 'non_resident_corr_bank_swift') {
                $this->merge([$key => strtoupper(preg_replace('/\s+/u', '', (string) $value))]);

                continue;
            }

            if ($key === 'cnaps_code') {
                $this->merge([$key => preg_replace('/\D/u', '', (string) $value)]);

                continue;
            }

            if ($key === 'non_resident_corr_settlement_account') {
                $this->merge([$key => preg_replace('/\D/u', '', (string) $value)]);

                continue;
            }

            $this->merge([$key => trim((string) $value)]);
        }

        if ($this->has('mail_sync_domains')) {
            $domains = $this->input('mail_sync_domains');

            if (is_string($domains)) {
                $domains = preg_split('/[\n,;]+/u', $domains) ?: [];
            }

            if (is_array($domains)) {
                $normalized = collect($domains)
                    ->map(fn (mixed $domain): string => PublicMailDomainCatalog::normalizeDomain((string) $domain))
                    ->filter(fn (string $domain): bool => $domain !== '' && ! PublicMailDomainCatalog::isPublic($domain))
                    ->unique()
                    ->values()
                    ->all();

                $this->merge([
                    'mail_sync_domains' => $normalized === [] ? null : $normalized,
                ]);
            }
        }

        $bankAccounts = $this->input('bank_accounts');
        if (is_array($bankAccounts)) {
            $normalized = collect($bankAccounts)
                ->map(function (mixed $account): array {
                    if (! is_array($account)) {
                        return [];
                    }

                    return [
                        'id' => isset($account['id']) ? (string) $account['id'] : null,
                        'label' => isset($account['label']) ? (string) $account['label'] : null,
                        'country_code' => isset($account['country_code']) ? strtoupper((string) $account['country_code']) : null,
                        'currency' => isset($account['currency']) ? strtoupper((string) $account['currency']) : null,
                        'bank_name' => isset($account['bank_name']) ? (string) $account['bank_name'] : null,
                        'bik' => isset($account['bik']) ? preg_replace('/\D/u', '', (string) $account['bik']) : null,
                        'account_number' => isset($account['account_number']) ? preg_replace('/\D/u', '', (string) $account['account_number']) : null,
                        'correspondent_account' => isset($account['correspondent_account']) ? preg_replace('/\D/u', '', (string) $account['correspondent_account']) : null,
                        'swift' => isset($account['swift']) ? strtoupper((string) $account['swift']) : null,
                        'iban' => isset($account['iban']) ? strtoupper(str_replace(' ', '', (string) $account['iban'])) : null,
                        'is_primary' => filter_var($account['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
                    ];
                })
                ->values()
                ->all();

            $this->merge(['bank_accounts' => $normalized]);
        }

        if ($this->has('name')) {
            $this->merge(['name' => ContractorIdentity::normalizeName($this->input('name'))]);
        }

        if ($this->has('inn')) {
            $this->merge(['inn' => ContractorIdentity::normalizeInn($this->input('inn'))]);
        }

        if ($this->has('has_english_requisites') && ! $this->boolean('has_english_requisites')) {
            $this->merge([
                'name_en' => null,
                'full_name_en' => null,
                'legal_address_en' => null,
                'actual_address_en' => null,
                'postal_address_en' => null,
                'contact_person_en' => null,
                'bank_name_en' => null,
                'signer_name_nominative_en' => null,
                'signer_name_prepositional_en' => null,
                'signer_position_en' => null,
                'signer_authority_basis_en' => null,
            ]);
        }

        foreach (['default_customer_payment_form', 'default_carrier_payment_form'] as $paymentFormKey) {
            if (! $this->has($paymentFormKey)) {
                continue;
            }

            $raw = $this->input($paymentFormKey);
            if (! is_string($raw) || trim($raw) === '') {
                continue;
            }

            $normalized = PaymentFormDictionary::normalizeForStorage($raw);
            if ($normalized !== null) {
                $this->merge([$paymentFormKey => $normalized]);
            }
        }

        $this->filterEmptyNestedCollections();
    }

    private function filterEmptyNestedCollections(): void
    {
        if ($this->has('contacts') && is_array($this->input('contacts'))) {
            $contacts = collect($this->input('contacts'))
                ->filter(fn (mixed $row): bool => is_array($row) && trim((string) ($row['full_name'] ?? '')) !== '')
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

                    if (($row['file'] ?? null) instanceof UploadedFile) {
                        return true;
                    }

                    return trim((string) ($row['title'] ?? '')) !== '';
                })
                ->values()
                ->all();

            $this->merge(['documents' => $documents]);
        }

        if ($this->has('interactions') && is_array($this->input('interactions'))) {
            $interactions = collect($this->input('interactions'))
                ->map(function (mixed $row): array {
                    if (! is_array($row)) {
                        return [];
                    }

                    if (array_key_exists('contacted_at', $row) && ($row['contacted_at'] === '' || $row['contacted_at'] === null)) {
                        $row['contacted_at'] = null;
                    }

                    return $row;
                })
                ->filter(fn (array $row): bool => ($row['contacted_at'] ?? null) !== null
                    || trim((string) ($row['subject'] ?? '')) !== ''
                    || trim((string) ($row['summary'] ?? '')) !== ''
                    || trim((string) ($row['result'] ?? '')) !== '')
                ->values()
                ->all();

            $this->merge(['interactions' => $interactions]);
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
            'type' => ['required', Rule::in(['customer', 'carrier', 'contractor', 'both'])],
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    ContractorDuplicateGuard::failIfNameTaken($value, $this->user(), null, $fail);
                },
            ],
            'full_name' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'inn' => [
                'nullable',
                'string',
                'max:20',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    ContractorDuplicateGuard::failIfInnTaken($value, $this->user(), null, $fail);
                },
            ],
            'kpp' => ['nullable', 'string', 'max:20'],
            'ogrn' => ['nullable', 'string', 'max:20'],
            'okpo' => ['nullable', 'string', 'max:20'],
            'legal_form' => ['nullable', Rule::in(['ooo', 'zao', 'ao', 'ip', 'samozanyaty', 'other'])],
            'legal_address' => ['nullable', 'string', 'max:255'],
            'actual_address' => ['nullable', 'string', 'max:255'],
            'postal_address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'mail_sync_domains' => ['nullable', 'array', 'max:20'],
            'mail_sync_domains.*' => ['string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_person_phone' => ['nullable', 'string', 'max:50'],
            'contact_person_email' => ['nullable', 'email', 'max:255'],
            'contact_person_position' => ['nullable', 'string', 'max:255'],
            'signer_name_nominative' => ['nullable', 'string', 'max:255'],
            'signer_name_prepositional' => ['nullable', 'string', 'max:255'],
            'signer_position' => ['nullable', 'string', 'max:255'],
            'signer_authority_basis' => ['nullable', 'string', 'max:255'],
            'edo_provider' => ['nullable', 'string', 'max:32', Rule::in(EdoProviderDictionary::codes())],
            'edo_number' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bik' => ['nullable', 'string', 'max:9'],
            'account_number' => ['nullable', 'string', 'max:20'],
            'correspondent_account' => ['nullable', 'string', 'max:20'],
            'bank_accounts' => ['nullable', 'array', 'max:20'],
            'bank_accounts.*.id' => ['nullable', 'string', 'max:100'],
            'bank_accounts.*.label' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.country_code' => ['nullable', 'string', 'size:2'],
            'bank_accounts.*.currency' => ['nullable', 'string', 'size:3', Rule::in(CurrencyDictionary::allowedCodes())],
            'bank_accounts.*.bank_name' => ['nullable', 'string', 'max:255'],
            'bank_accounts.*.bik' => ['nullable', 'digits:9'],
            'bank_accounts.*.account_number' => ['nullable', 'digits_between:5,34'],
            'bank_accounts.*.correspondent_account' => ['nullable', 'digits_between:5,34'],
            'bank_accounts.*.swift' => ['nullable', 'string', 'min:8', 'max:11'],
            'bank_accounts.*.iban' => ['nullable', 'string', 'min:10', 'max:34'],
            'bank_accounts.*.is_primary' => ['nullable', 'boolean'],
            'ati_id' => ['nullable', 'string', 'max:50'],
            'specializations' => ['nullable', 'array'],
            'specializations.*' => ['string', 'max:255'],
            'activity_types' => ['nullable', 'array'],
            'activity_types.*' => ['string', 'max:255'],
            'transport_requirements' => ['nullable', 'array'],
            'transport_requirements.*' => ['string', 'max:255'],
            'debt_limit' => ['nullable', 'numeric', 'min:0'],
            'debt_limit_currency' => ['nullable', Rule::in(CurrencyDictionary::allowedCodes())],
            'stop_on_limit' => ['required', 'boolean'],
            'default_customer_payment_form' => ['nullable', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'default_customer_payment_term' => ['nullable', 'string', 'max:255'],
            'default_customer_payment_schedule' => ['nullable', 'array'],
            'default_customer_payment_schedule.has_prepayment' => ['nullable', 'boolean'],
            'default_customer_payment_schedule.prepayment_ratio' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'default_customer_payment_schedule.prepayment_days' => ['nullable', 'integer', 'min:0'],
            'default_customer_payment_schedule.prepayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn'])],
            'default_customer_payment_schedule.postpayment_days' => ['nullable', 'integer', 'min:0'],
            'default_customer_payment_schedule.postpayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn'])],
            'default_carrier_payment_form' => ['nullable', Rule::in(PaymentFormDictionary::allowedCodesForValidation())],
            'default_carrier_payment_term' => ['nullable', 'string', 'max:255'],
            'default_carrier_payment_schedule' => ['nullable', 'array'],
            'default_carrier_payment_schedule.has_prepayment' => ['nullable', 'boolean'],
            'default_carrier_payment_schedule.prepayment_ratio' => ['nullable', 'numeric', 'min:1', 'max:99'],
            'default_carrier_payment_schedule.prepayment_days' => ['nullable', 'integer', 'min:0'],
            'default_carrier_payment_schedule.prepayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn'])],
            'default_carrier_payment_schedule.postpayment_days' => ['nullable', 'integer', 'min:0'],
            'default_carrier_payment_schedule.postpayment_mode' => ['nullable', Rule::in(['fttn', 'fttn_receipt', 'ottn'])],
            'cooperation_terms_notes' => ['nullable', 'string'],
            ...PartyNormsPenalties::validationRules('default_customer_norms_penalties'),
            ...PartyNormsPenalties::validationRules('default_carrier_norms_penalties'),
            'is_active' => ['required', 'boolean'],
            'work_status' => ['nullable', 'string', Rule::in(ContractorWorkStatus::manualValues())],
            'is_verified' => ['sometimes', 'boolean'],
            'is_own_company' => ['required', 'boolean'],
            'is_non_resident' => ['nullable', 'boolean'],
            'has_english_requisites' => ['nullable', 'boolean'],
            'name_en' => ['nullable', 'string', 'max:255'],
            'full_name_en' => ['nullable', 'string', 'max:255'],
            'legal_address_en' => ['nullable', 'string', 'max:255'],
            'actual_address_en' => ['nullable', 'string', 'max:255'],
            'postal_address_en' => ['nullable', 'string', 'max:255'],
            'contact_person_en' => ['nullable', 'string', 'max:255'],
            'bank_name_en' => ['nullable', 'string', 'max:255'],
            'signer_name_nominative_en' => ['nullable', 'string', 'max:255'],
            'signer_name_prepositional_en' => ['nullable', 'string', 'max:255'],
            'signer_position_en' => ['nullable', 'string', 'max:255'],
            'signer_authority_basis_en' => ['nullable', 'string', 'max:255'],
            'non_resident_corr_bank_name' => ['nullable', 'string', 'max:255'],
            'non_resident_corr_bank_swift' => ['nullable', 'string', 'max:11', 'regex:/^[A-Za-z0-9]*$/u'],
            'non_resident_corr_settlement_account' => ['nullable', 'string', 'max:34', 'regex:/^[0-9]*$/u'],
            'non_resident_corr_bank_account' => ['nullable', 'string', 'max:64'],
            'cnaps_code' => ['nullable', 'string', 'max:20', 'regex:/^[0-9]*$/u'],
            'owner_id' => $this->ownerIdRules(),
            'contacts' => ['nullable', 'array'],
            'contacts.*.full_name' => ['required', 'string', 'max:255'],
            'contacts.*.position' => ['nullable', 'string', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:50'],
            'contacts.*.email' => ['nullable', 'email', 'max:255'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
            'contacts.*.is_decision_maker' => ['nullable', 'boolean'],
            'contacts.*.role_in_deal' => ['nullable', 'string', 'max:32'],
            'contacts.*.communication_notes' => ['nullable', 'string', 'max:2000'],
            'contacts.*.notes' => ['nullable', 'string'],
            'interactions' => ['nullable', 'array'],
            'interactions.*.contacted_at' => ['nullable', 'date'],
            'interactions.*.channel' => ['nullable', 'string', 'max:50'],
            'interactions.*.subject' => ['nullable', 'string', 'max:255'],
            'interactions.*.summary' => ['nullable', 'string'],
            'interactions.*.result' => ['nullable', 'string', 'max:255'],
            'documents' => ['nullable', 'array'],
            'documents.*.id' => ['nullable', 'integer'],
            'documents.*.type' => ['nullable', 'string', 'max:255'],
            'documents.*.title' => ['required', 'string', 'max:255'],
            'documents.*.number' => ['nullable', 'string', 'max:255'],
            'documents.*.document_date' => ['nullable', 'date'],
            'documents.*.status' => ['nullable', 'string', 'max:255'],
            'documents.*.notes' => ['nullable', 'string'],
            'documents.*.file' => [
                'nullable',
                'file',
                'max:'.DocumentUploadBudget::absoluteMaxKilobytes(),
                new DocumentWithinPageBudget,
            ],
        ];
    }

    /**
     * Пустые строки из JSON contractor_payload не проходят ConvertEmptyStringsToNull.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeEmptyStringsToNull(array $data): array
    {
        foreach ($data as $key => $value) {
            if ($value === '') {
                $data[$key] = null;

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->normalizeEmptyStringsToNull($value);
            }
        }

        return $data;
    }

    /**
     * @return array<int, ValidationRule|string>
     */
    protected function ownerIdRules(): array
    {
        return [
            'nullable',
            'integer',
            Rule::exists('users', 'id')->where('is_active', true),
        ];
    }
}
