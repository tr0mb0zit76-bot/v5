<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Support\Facades\Schema;

class OrderCompensationSplitResolver
{
    /**
     * @return array{
     *     active: bool,
     *     owner_percent: float,
     *     dispatcher_percent: float,
     *     dispatcher_id: int|null
     * }
     */
    public static function fromOrder(Order $order): array
    {
        $metadata = is_array($order->metadata) ? $order->metadata : [];
        $split = is_array($metadata['compensation_split'] ?? null) ? $metadata['compensation_split'] : [];

        $dispatcherId = null;
        if (Schema::hasColumn('orders', 'dispatcher_id') && $order->dispatcher_id !== null) {
            $dispatcherId = (int) $order->dispatcher_id;
        } elseif (isset($split['dispatcher_id']) && (int) $split['dispatcher_id'] > 0) {
            $dispatcherId = (int) $split['dispatcher_id'];
        }

        if ($dispatcherId === null) {
            return self::noSplit();
        }

        return [
            'active' => true,
            'owner_percent' => (float) ($split['order_owner_percent'] ?? 100),
            'dispatcher_percent' => (float) ($split['dispatcher_percent'] ?? 0),
            'dispatcher_id' => $dispatcherId,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     active: bool,
     *     owner_percent: float,
     *     dispatcher_percent: float,
     *     dispatcher_id: int|null
     * }
     */
    public static function fromPayload(array $data): array
    {
        $dispatcherId = $data['dispatcher_id'] ?? null;
        if ($dispatcherId === null || $dispatcherId === '' || (int) $dispatcherId <= 0) {
            return self::noSplit();
        }

        $ownerPercent = array_key_exists('compensation_owner_percent', $data)
            ? (float) $data['compensation_owner_percent']
            : 100.0;
        $dispatcherPercent = array_key_exists('compensation_dispatcher_percent', $data)
            ? (float) $data['compensation_dispatcher_percent']
            : 0.0;

        return [
            'active' => true,
            'owner_percent' => $ownerPercent,
            'dispatcher_percent' => $dispatcherPercent,
            'dispatcher_id' => (int) $dispatcherId,
        ];
    }

    /**
     * @param  array{active: bool, owner_percent: float, dispatcher_percent: float, dispatcher_id: int|null}  $split
     * @return array{total: float, owner: float, dispatcher: float}
     */
    public static function applyToSalary(float $totalSalary, array $split): array
    {
        if (! ($split['active'] ?? false)) {
            return [
                'total' => round($totalSalary, 2),
                'owner' => round($totalSalary, 2),
                'dispatcher' => 0.0,
            ];
        }

        $owner = round($totalSalary * ((float) $split['owner_percent']) / 100, 2);
        $dispatcher = round($totalSalary * ((float) $split['dispatcher_percent']) / 100, 2);

        return [
            'total' => round($totalSalary, 2),
            'owner' => $owner,
            'dispatcher' => $dispatcher,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  array{total: float, owner: float, dispatcher: float}  $salarySplit
     * @return array<string, mixed>
     */
    public static function mergeSalaryIntoMetadata(array $metadata, array $salarySplit): array
    {
        $existing = is_array($metadata['compensation_split'] ?? null) ? $metadata['compensation_split'] : [];

        $metadata['compensation_split'] = [
            ...$existing,
            'salary_accrued_total' => $salarySplit['total'],
            'salary_accrued_owner' => $salarySplit['owner'],
            'salary_accrued_dispatcher' => $salarySplit['dispatcher'],
        ];

        return $metadata;
    }

    /**
     * @return array{active: bool, owner_percent: float, dispatcher_percent: float, dispatcher_id: int|null}
     */
    private static function noSplit(): array
    {
        return [
            'active' => false,
            'owner_percent' => 100.0,
            'dispatcher_percent' => 0.0,
            'dispatcher_id' => null,
        ];
    }
}
