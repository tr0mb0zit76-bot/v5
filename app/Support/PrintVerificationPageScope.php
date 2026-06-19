<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Contractor;

/**
 * Какие контрагенты показывать на публичной странице проверки QR по документу.
 */
final class PrintVerificationPageScope
{
    public const PARTY_CUSTOMER = 'customer';

    public const PARTY_CARRIER = 'carrier';

    public static function partyFromMetadata(array $metadata): ?string
    {
        $party = is_string($metadata['party'] ?? null) ? trim($metadata['party']) : '';

        return in_array($party, [self::PARTY_CUSTOMER, self::PARTY_CARRIER], true) ? $party : null;
    }

    /**
     * @return list<array{label: string, name: string}>
     */
    public static function counterpartyRows(?string $party, ?Contractor $customer, ?Contractor $carrier): array
    {
        return match ($party) {
            self::PARTY_CUSTOMER => self::singleRow('Заказчик', $customer),
            self::PARTY_CARRIER => self::singleRow('Перевозчик', $carrier),
            default => [],
        };
    }

    /**
     * @return list<array{label: string, name: string}>
     */
    private static function singleRow(string $label, ?Contractor $contractor): array
    {
        $name = $contractor !== null && filled($contractor->name)
            ? trim((string) $contractor->name)
            : '';

        if ($name === '') {
            return [];
        }

        return [['label' => $label, 'name' => $name]];
    }
}
