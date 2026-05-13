<?php

namespace App\Support;

/**
 * Единая логика: куда смотреть в снимке данных при подстановке плейсхолдера DOCX.
 * Должна совпадать с OrderPrintFormDraftService / LeadPrintFormDraftService при генерации.
 */
class PrintFormPlaceholderPathResolver
{
    /**
     * Короткие плейсхолдеры из старых макетов, которые в legacy-карте ведут на customer.*.
     * Для шаблона заказа со стороной «перевозчик» подставляем реквизиты перевозчика (carrier.*).
     *
     * @var list<string>
     */
    private const LEGACY_ORDER_CUSTOMER_CONTRACTOR_KEYS = [
        'poln_nazv_zak',
        'kratk_nazv_zak',
        'inn',
        'kpp',
        'ogrn',
        'yur_address',
        'pocht_address',
        'bank',
        'bik',
        'r/s',
        'k/s',
        'fio_podpisant',
        'fio_podpisant_im',
        'fio_podpisant_rod',
        'dolzhn_podpisant',
        'bank_korresp',
        'swift_korresp',
        'cnaps_kod',
        'rs_banka_korresp',
        'schet_v_banku_korresp',
    ];

    /**
     * @param  array<string, mixed>  $variableMapping
     * @param  string|null  $orderParty  Сторона шаблона заказа (customer|carrier|internal); учитывается только для entityType order.
     */
    public function resolve(string $placeholder, array $variableMapping, string $entityType, ?string $orderParty = null): string
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
        $fromLegacy = $legacy[$normalized] ?? null;

        if ($fromLegacy !== null) {
            if ($entityType === 'order' && $orderParty === 'carrier' && in_array($normalized, self::LEGACY_ORDER_CUSTOMER_CONTRACTOR_KEYS, true)) {
                if (str_starts_with($fromLegacy, 'customer.')) {
                    return 'carrier.'.substr($fromLegacy, strlen('customer.'));
                }
            }

            return $fromLegacy;
        }

        return $placeholder;
    }

    /**
     * @param  list<string>  $placeholders
     * @param  array<string, mixed>  $variableMapping
     * @return array<string, string>
     */
    public function effectiveVariableMapping(array $placeholders, array $variableMapping, string $entityType, ?string $orderParty = null): array
    {
        $out = [];
        foreach ($placeholders as $placeholder) {
            if (! is_string($placeholder) || $placeholder === '') {
                continue;
            }
            $out[$placeholder] = $this->resolve($placeholder, $variableMapping, $entityType, $orderParty);
        }

        return $out;
    }

    private function normalizeLegacyPlaceholderKey(string $placeholder): string
    {
        $value = str_replace(["\u{2019}", "\u{2018}", "\u{00B4}", '’', '`', '´'], "'", trim($placeholder));
        $value = mb_strtolower($value, 'UTF-8');

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
            'vremya_zagruzki' => 'route.loading_time_range',
            'vremya_vygruzki' => 'route.unloading_time_range',
            'address_zagruzki' => 'route.loading_first_address',
            'address_vygruzki' => 'route.unloading_first_address',
            'gorod_zagruzki' => 'route.loading_first_city',
            'gorod_vygruzki' => 'route.unloading_first_city',
            'gorod_vygruzki_posledniy' => 'route.unloading_last_city',
            'gorod_posledney_vygruzki' => 'route.unloading_last_city',
            'gruzootpav' => 'cargo_sender.name',
            'gruzootpavitel' => 'cargo_sender.name',
            'gruzopoluchatel' => 'cargo_recipient.name',
            'sposob_pogruzki' => 'route.loading_method',
            'kontakt_na_zagruzke' => 'cargo_sender.contact_phone',
            'kontakt_na_vygruzke' => 'cargo_recipient.contact_phone',
            'cargo_summary' => 'cargo.summary',
            'stoimost' => 'order.customer_rate_with_currency',
            'stoimost\'_zak' => 'order.customer_rate_with_currency',
            'stoimost\'_perevoz' => 'order.carrier_rate_with_currency',
            'forma_oplaty' => 'order.customer_payment_form',
            'usloviya_oplaty' => 'order.customer_payment_term',
            'primechanya' => 'order.special_notes',
            'manager' => 'manager.name',
            'manager_email' => 'manager.email',
            'manager_tel' => 'manager.phone',
            'responsible_name' => 'responsible.name',
            'responsible_email' => 'responsible.email',
            'responsible_tel' => 'responsible.phone',
            'compania' => 'own_company.name',
            'svh' => 'order.svh_name',
            'adres_svh' => 'order.svh_address',
            'kod_posta' => 'order.customs_post_code',
            'nazvanie_posta' => 'order.svh_name',
            'mesto_oformleniya_ex' => 'order.customs_declaration_place',
            'kod_tn_ved' => 'cargo.first_hs_code',
            'gruz_1' => 'cargo.line_1_summary',
            'gruz_2' => 'cargo.line_2_summary',
            'gruz_3' => 'cargo.line_3_summary',
            'gruz_4' => 'cargo.line_4_summary',
            'gruz_5' => 'cargo.line_5_summary',
            'cargo_name1' => 'cargo.line_1_name',
            'cargo_name2' => 'cargo.line_2_name',
            'cargo_name3' => 'cargo.line_3_name',
            'cargo_name4' => 'cargo.line_4_name',
            'cargo_name5' => 'cargo.line_5_name',
            'kontakt_perevoz' => 'contacts.carrier_name',
            'kontakt_perevoz_tel' => 'contacts.carrier_phone',
            'kontakt_perevoz_email' => 'contacts.carrier_email',
            'bik_perev' => 'carrier.bik',
            'lp_bik' => 'customer.bik',
            'lp_kpp' => 'customer.kpp',
            'lp_ogrn' => 'customer.ogrn',
            'lp_ yur_address' => 'customer.legal_address',
            'lp_ceo' => 'customer.signer_name_nominative',
            'lp_ceo_title' => 'customer.signer_position',
            'lp_bank' => 'customer.bank_name',
            'lp_ks' => 'customer.correspondent_account',
            'lp_rs' => 'customer.account_number',
            'lp_pocht_address' => 'customer.postal_address',
            'lp_inn' => 'customer.inn',
            'lp_yur_address' => 'customer.legal_address',
            'class_opasnosti' => 'cargo.hazard_classes',
            'osobye_uslovia_pogruzki' => 'cargo.loading_types',
            'fio_voditel' => 'driver.full_name',
            'tel_voditel' => 'driver.phone',
            'passport_voditel' => 'driver.passport_data',
            'marka_avto' => 'vehicle.brand',
            'gosnomer' => 'vehicle.number',
            'tip_pritsepa' => 'vehicle.cargo_body_type',
            'tip_prizepa' => 'vehicle.cargo_body_type',
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
            'fio_podpisant_im' => 'customer.signer_name_nominative',
            'fio_podpisant_rod' => 'customer.signer_name_prepositional',
            'dolzhn_podpisant' => 'customer.signer_position',
            'dolzhn_podpisant_rod' => 'customer.signer_position_genitive_auto',
            'poln_nazv_perevoz' => 'carrier.full_name',
            'kratk_nazv_perev' => 'carrier.name',
            'inn_perev' => 'carrier.inn',
            'kpp_perev' => 'carrier.kpp',
            'ogrn_perev' => 'carrier.ogrn',
            'yur_address_per' => 'carrier.legal_address',
            'pocht_address_perev' => 'carrier.actual_address',
            'bank_perev' => 'carrier.bank_name',
            'bik_perev' => 'carrier.bik',
            'r/s_perev' => 'carrier.account_number',
            'k/s_perev' => 'carrier.correspondent_account',
            'fio_podpisant_perevoz_im' => 'carrier.signer_name_nominative',
            'fio_podpisant_perevoz_rod' => 'carrier.signer_name_prepositional',
            'podpisant_perevoz' => 'carrier.signer_name_nominative',
            'dolzhn_podpisant_perevoz' => 'carrier.signer_position',
            'podpisant_perevoz_rod' => 'carrier.signer_position_genitive_auto',
            'bank_korresp' => 'customer.non_resident_corr_bank_name',
            'swift_korresp' => 'customer.non_resident_corr_bank_swift',
            'cnaps_kod' => 'customer.cnaps_code',
            'rs_banka_korresp' => 'customer.non_resident_corr_settlement_account',
            'schet_v_banku_korresp' => 'customer.non_resident_corr_bank_account',
            'bank_korresp_perev' => 'carrier.non_resident_corr_bank_name',
            'swift_korresp_perev' => 'carrier.non_resident_corr_bank_swift',
            'cnaps_kod_perev' => 'carrier.cnaps_code',
            'rs_banka_korresp_perev' => 'carrier.non_resident_corr_settlement_account',
            'schet_v_banku_korresp_perev' => 'carrier.non_resident_corr_bank_account',
        ];
    }
}
