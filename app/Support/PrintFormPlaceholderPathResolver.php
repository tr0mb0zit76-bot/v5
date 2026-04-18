<?php

namespace App\Support;

/**
 * Единая логика: куда смотреть в снимке данных при подстановке плейсхолдера DOCX.
 * Должна совпадать с OrderPrintFormDraftService / LeadPrintFormDraftService при генерации.
 */
class PrintFormPlaceholderPathResolver
{
    /**
     * @param  array<string, mixed>  $variableMapping
     */
    public function resolve(string $placeholder, array $variableMapping, string $entityType): string
    {
        $explicit = $variableMapping[$placeholder] ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        if ($entityType === 'lead') {
            return $placeholder;
        }

        $legacy = $this->legacyPlaceholderMappings();
        $normalized = $this->normalizeLegacyPlaceholderKey($placeholder);

        return $legacy[$normalized] ?? $placeholder;
    }

    /**
     * @param  list<string>  $placeholders
     * @param  array<string, mixed>  $variableMapping
     * @return array<string, string>
     */
    public function effectiveVariableMapping(array $placeholders, array $variableMapping, string $entityType): array
    {
        $out = [];
        foreach ($placeholders as $placeholder) {
            if (! is_string($placeholder) || $placeholder === '') {
                continue;
            }
            $out[$placeholder] = $this->resolve($placeholder, $variableMapping, $entityType);
        }

        return $out;
    }

    private function normalizeLegacyPlaceholderKey(string $placeholder): string
    {
        $value = mb_strtolower(trim($placeholder), 'UTF-8');
        $value = str_replace(['’', '`', '´'], '', $value);

        return $value;
    }

    /**
     * @return array<string, string>
     */
    private function legacyPlaceholderMappings(): array
    {
        return [
            'nomer_zayavki' => 'order.order_number',
            'data_zakaza' => 'order.order_date',
            'data_zagruzki' => 'order.loading_date',
            'data_vygruzki' => 'order.unloading_date',
            'vremya_zagruzki' => 'route.loading_time_from',
            'vremya_vygruzki' => 'route.unloading_time_from',
            'address_zagruzki' => 'route.loading_first_address',
            'address_vygruzki' => 'route.unloading_first_address',
            'gorod_zagruzki' => 'route.loading_first_city',
            'gorod_vygruzki' => 'route.unloading_first_city',
            'gruzootpav' => 'cargo_sender.name',
            'gruzopoluchatel' => 'cargo_recipient.name',
            'kontakt_na_zagruzke' => 'cargo_sender.contact_phone',
            'kontakt_na_vygruzke' => 'cargo_recipient.contact_phone',
            'cargo_summary' => 'cargo.summary',
            'stoimost' => 'order.customer_rate',
            'forma_oplaty' => 'order.customer_payment_form',
            'usloviya_oplaty' => 'order.customer_payment_term',
            'primechanya' => 'order.special_notes',
            'fio_voditel' => 'driver.full_name',
            'tel_voditel' => 'driver.phone',
            'passport_voditel' => 'driver.passport_data',
            'marka_avto' => 'vehicle.brand',
            'gosnomer' => 'vehicle.number',
            'tip_pritsepa' => 'vehicle.transport_type',
            'tip_prizepa' => 'vehicle.transport_type',
            'poln_nazv_zak' => 'customer.full_name',
            'kratk_nazv_zak' => 'customer.name',
            'inn' => 'customer.inn',
            'kpp' => 'customer.kpp',
            'ogrn' => 'customer.ogrn',
            'yur_address' => 'customer.legal_address',
            'pocht_address' => 'customer.actual_address',
            'bank' => 'customer.bank_name',
            'bik' => 'customer.bik',
            'r/s' => 'customer.account_number',
            'k/s' => 'customer.correspondent_account',
            'fio_podpisant' => 'customer.signer_name_nominative',
            'fio_podpisant_rod' => 'customer.signer_name_prepositional',
            'dolzhn_podpisant' => 'customer.signer_position',
        ];
    }
}
