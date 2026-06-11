<?php

namespace App\Support;

final class ManagementCostCategoryCodes
{
    /** Привлечённый транспорт (legacy code operational_carrier_out). */
    public const HIRED_TRANSPORT = 'operational_carrier_out';

    public const OWN_FLEET = 'cost_own_fleet';

    /**
     * @return list<string>
     */
    public static function costLeafCodes(): array
    {
        return [
            self::HIRED_TRANSPORT,
            self::OWN_FLEET,
        ];
    }
}
