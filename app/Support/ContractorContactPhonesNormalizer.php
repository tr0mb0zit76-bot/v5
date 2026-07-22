<?php

namespace App\Support;

final class ContractorContactPhonesNormalizer
{
    public const KINDS = ['work', 'personal', 'mobile', 'other'];

    /**
     * @param  array<string, mixed>  $contact
     * @return array{phones: list<array{number: string, kind: string, is_primary: bool}>, phone: string|null}
     */
    public static function normalizeContactPhones(array $contact): array
    {
        $phonesInput = $contact['phones'] ?? null;
        $legacyPhone = self::trimNullableString($contact['phone'] ?? null);

        $phones = [];

        if (is_array($phonesInput)) {
            foreach ($phonesInput as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $number = self::trimNullableString($row['number'] ?? null);
                if ($number === null) {
                    continue;
                }

                $kind = strtolower(trim((string) ($row['kind'] ?? 'work')));
                if (! in_array($kind, self::KINDS, true)) {
                    $kind = 'other';
                }

                $phones[] = [
                    'number' => $number,
                    'kind' => $kind,
                    'is_primary' => (bool) ($row['is_primary'] ?? false),
                ];
            }
        }

        if ($phones === [] && $legacyPhone !== null) {
            $phones[] = [
                'number' => $legacyPhone,
                'kind' => 'work',
                'is_primary' => true,
            ];
        }

        if ($phones === []) {
            return [
                'phones' => [],
                'phone' => null,
            ];
        }

        $hasPrimary = false;
        foreach ($phones as $phone) {
            if ($phone['is_primary']) {
                $hasPrimary = true;
                break;
            }
        }

        if (! $hasPrimary) {
            $phones[0]['is_primary'] = true;
        } else {
            $seenPrimary = false;
            foreach ($phones as $index => $phone) {
                if (! $phone['is_primary']) {
                    continue;
                }
                if ($seenPrimary) {
                    $phones[$index]['is_primary'] = false;
                } else {
                    $seenPrimary = true;
                }
            }
        }

        $primaryNumber = $phones[0]['number'];
        foreach ($phones as $phone) {
            if ($phone['is_primary']) {
                $primaryNumber = $phone['number'];
                break;
            }
        }

        return [
            'phones' => array_values($phones),
            'phone' => $primaryNumber,
        ];
    }

    private static function trimNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
