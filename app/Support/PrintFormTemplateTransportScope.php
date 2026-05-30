<?php

namespace App\Support;

final class PrintFormTemplateTransportScope
{
    public const ANY = 'any';

    public const DOMESTIC = 'domestic';

    public const INTERNATIONAL = 'international';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::ANY,
            self::DOMESTIC,
            self::INTERNATIONAL,
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => self::ANY, 'label' => 'Любая перевозка'],
            ['value' => self::DOMESTIC, 'label' => 'Внутрироссийская'],
            ['value' => self::INTERNATIONAL, 'label' => 'Международная (ВЭД)'],
        ];
    }

    public static function label(?string $scope): string
    {
        return match ($scope) {
            self::DOMESTIC => 'Внутрироссийская',
            self::INTERNATIONAL => 'Международная (ВЭД)',
            default => 'Любая перевозка',
        };
    }

    public static function matches(?string $scope, bool $isInternationalTransport): bool
    {
        $scope = trim((string) ($scope ?? self::ANY));

        return match ($scope) {
            self::DOMESTIC => ! $isInternationalTransport,
            self::INTERNATIONAL => $isInternationalTransport,
            default => true,
        };
    }
}
