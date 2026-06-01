<?php

namespace App\Support;

use App\Models\Contractor;

/**
 * Значения плейсхолдеров DOCX «Карта партнёра» из карточки контрагента (своя компания).
 */
final class ContractorPartnerCardSnapshot
{
    /**
     * @return array<string, string>
     */
    public static function replacements(Contractor $contractor): array
    {
        $bank = $contractor->bankDetailsFromAccountsFallback();
        $fullName = self::stringOrEmpty($contractor->full_name) ?: self::stringOrEmpty($contractor->name);
        $shortName = self::stringOrEmpty($contractor->name);
        $header = self::splitHeaderLines($fullName, $shortName);

        return [
            'kp_header_line1' => $header['line1'],
            'kp_header_name' => $header['name'],
            'kp_full_name' => $fullName,
            'kp_short_name' => $shortName,
            'kp_legal_address' => self::stringOrEmpty($contractor->legal_address),
            'kp_postal_address' => self::stringOrEmpty($contractor->postal_address) ?: self::stringOrEmpty($contractor->actual_address),
            'kp_ogrn' => self::stringOrEmpty($contractor->ogrn),
            'kp_okved' => self::resolveOkved($contractor),
            'kp_inn' => self::stringOrEmpty($contractor->inn),
            'kp_kpp' => self::stringOrEmpty($contractor->kpp),
            'kp_rs_rub' => self::accountNumberForCurrency($contractor, 'RUB') ?: self::stringOrEmpty($bank['account_number']),
            'kp_rs_cny' => self::accountNumberForCurrency($contractor, 'CNY'),
            'kp_bank' => self::stringOrEmpty($bank['bank_name']) ?: self::stringOrEmpty($contractor->bank_name),
            'kp_ks' => self::stringOrEmpty($bank['correspondent_account']) ?: self::stringOrEmpty($contractor->correspondent_account),
            'kp_bik' => self::stringOrEmpty($bank['bik']) ?: self::stringOrEmpty($contractor->bik),
            'kp_ceo_title' => self::stringOrEmpty($contractor->signer_position) ?: self::stringOrEmpty($contractor->contact_person_position),
            'kp_ceo_fio' => self::stringOrEmpty($contractor->signer_name_nominative),
            'kp_ceo_basis' => self::stringOrEmpty($contractor->signer_authority_basis),
            'kp_phone' => self::stringOrEmpty($contractor->phone),
            'kp_email' => self::stringOrEmpty($contractor->email),
            'kp_edo_provider' => EdoProviderDictionary::label($contractor->edo_provider) ?? '',
            'kp_edo_number' => self::stringOrEmpty($contractor->edo_number),
        ];
    }

    /**
     * @return array{line1: string, name: string}
     */
    private static function splitHeaderLines(string $fullName, string $shortName): array
    {
        if (preg_match('/^(.+?)\s*[«"]([^»"]+)[»"]\s*$/u', $fullName, $matches) === 1) {
            return [
                'line1' => trim($matches[1]),
                'name' => trim($matches[2]),
            ];
        }

        if ($shortName !== '' && $shortName !== $fullName) {
            return [
                'line1' => $fullName,
                'name' => $shortName,
            ];
        }

        return [
            'line1' => 'Общество с ограниченной ответственностью',
            'name' => $shortName !== '' ? $shortName : $fullName,
        ];
    }

    private static function resolveOkved(Contractor $contractor): string
    {
        $metadata = $contractor->metadata;
        if (is_array($metadata)) {
            foreach (['okved', 'okved_main', 'main_okved'] as $key) {
                $value = $metadata[$key] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    return trim($value);
                }
            }
        }

        $activityTypes = $contractor->activity_types;
        if (! is_array($activityTypes) || $activityTypes === []) {
            return '';
        }

        $labels = collect($activityTypes)
            ->map(function (mixed $row): ?string {
                if (is_string($row)) {
                    return trim($row) !== '' ? trim($row) : null;
                }

                if (! is_array($row)) {
                    return null;
                }

                $name = trim((string) ($row['name'] ?? $row['label'] ?? ''));

                return $name !== '' ? $name : null;
            })
            ->filter()
            ->values();

        return $labels->implode('; ');
    }

    private static function accountNumberForCurrency(Contractor $contractor, string $currency): string
    {
        $currency = strtoupper($currency);
        $accounts = $contractor->bank_accounts;

        if (! is_array($accounts)) {
            return '';
        }

        foreach ($accounts as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowCurrency = strtoupper((string) ($row['currency'] ?? 'RUB'));
            if ($rowCurrency !== $currency) {
                continue;
            }

            $account = self::stringOrEmpty($row['account_number'] ?? null);

            if ($account !== '') {
                return $account;
            }
        }

        return '';
    }

    private static function stringOrEmpty(mixed $value): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return trim((string) $value);
    }
}
