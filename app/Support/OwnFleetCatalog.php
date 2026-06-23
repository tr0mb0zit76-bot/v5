<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Contractor;

final class OwnFleetCatalog
{
    public const EXECUTION_MODE_OWN_FLEET = 'own_fleet';

    public const CONTRACTOR_NAME = 'Собственный парк';

    /** Подпись в UI (перевозчик, меню, отчёты) — совпадает с именем контрагента. */
    public const UI_LABEL = self::CONTRACTOR_NAME;

    public static function isVirtualFleetContractorName(?string $name): bool
    {
        if ($name === null) {
            return false;
        }

        return trim($name) === self::CONTRACTOR_NAME;
    }

    public static function isVirtualFleetContractor(?Contractor $contractor): bool
    {
        if ($contractor === null) {
            return false;
        }

        return self::isVirtualFleetContractorName($contractor->name);
    }

    /**
     * @return list<string>
     */
    public static function costCategoryCodes(): array
    {
        return array_keys(self::costCategoryLabels());
    }

    /**
     * @return array<string, string>
     */
    public static function costCategoryLabels(): array
    {
        return [
            'fuel' => 'Топливо',
            'driver_salary' => 'Зарплата / сдельная оплата водителя',
            'per_diem' => 'Командировочные / суточные',
            'toll' => 'Платные дороги',
            'repair' => 'Ремонт по рейсу',
            'other' => 'Прочее',
        ];
    }

    /**
     * @return list<string>
     */
    public static function tripStatusCodes(): array
    {
        return ['planned', 'in_progress', 'completed', 'cancelled'];
    }

    /**
     * @return array<string, string>
     */
    public static function tripStatusLabels(): array
    {
        return [
            'planned' => 'Запланирован',
            'in_progress' => 'В пути',
            'completed' => 'Завершён',
            'cancelled' => 'Отменён',
        ];
    }

    public static function isOwnFleetExecutionMode(?string $mode): bool
    {
        return $mode === self::EXECUTION_MODE_OWN_FLEET;
    }

    /**
     * Все слоты перевозчика на заказе — «Свой транспорт» (нет внешних перевозчиков).
     *
     * @param  list<array<string, mixed>>  $performers
     */
    public static function isOwnFleetCarrierOnly(array $performers): bool
    {
        $expanded = self::expandCarrierExecutionRows($performers);

        if ($expanded === []) {
            return false;
        }

        foreach ($expanded as $row) {
            if (! self::isOwnFleetExecutionMode(isset($row['execution_mode']) ? (string) $row['execution_mode'] : null)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $performers
     * @return list<array{execution_mode?: string|null}>
     */
    public static function expandCarrierExecutionRows(array $performers): array
    {
        $expanded = [];

        foreach ($performers as $performer) {
            if (! is_array($performer)) {
                continue;
            }

            if (($performer['carrier_mode'] ?? 'single') === 'split' && is_array($performer['split_carriers'] ?? null)) {
                foreach ($performer['split_carriers'] as $slot) {
                    if (! is_array($slot)) {
                        continue;
                    }

                    $expanded[] = [
                        'execution_mode' => isset($slot['execution_mode']) ? (string) $slot['execution_mode'] : null,
                    ];
                }

                continue;
            }

            $expanded[] = [
                'execution_mode' => isset($performer['execution_mode']) ? (string) $performer['execution_mode'] : null,
            ];
        }

        return $expanded;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function performerRowIsOwnFleet(array $row): bool
    {
        if (self::isOwnFleetExecutionMode(isset($row['execution_mode']) ? (string) $row['execution_mode'] : null)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $performer
     */
    public static function resolveExecutionModeForSlot(array $performer, ?int $carrierSlot): ?string
    {
        if (($performer['carrier_mode'] ?? 'single') === 'split' && $carrierSlot !== null) {
            foreach ($performer['split_carriers'] ?? [] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }

                if ((int) ($slot['slot'] ?? 0) === $carrierSlot) {
                    $mode = $slot['execution_mode'] ?? null;

                    return is_string($mode) && $mode !== '' ? $mode : null;
                }
            }

            return null;
        }

        $mode = $performer['execution_mode'] ?? null;

        return is_string($mode) && $mode !== '' ? $mode : null;
    }
}
