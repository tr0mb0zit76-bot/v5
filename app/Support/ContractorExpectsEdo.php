<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Мягкий контур: ЭДО ожидается, если у контрагента заполнен провайдер или номер.
 */
final class ContractorExpectsEdo
{
    public static function fromFields(mixed $provider, mixed $number): bool
    {
        return filled(trim((string) ($provider ?? '')))
            || filled(trim((string) ($number ?? '')));
    }
}
