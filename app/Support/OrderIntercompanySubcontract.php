<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Order;

/**
 * Межфирменный субподряд: 1-я рука (own_company) ↔ 2-я рука (carrier_own_company).
 *
 * Сумма: customer_rate × 0.95. Форма оплаты / НДС — с клиентской стороны.
 */
final class OrderIntercompanySubcontract
{
    public const RATE_FACTOR = 0.95;

    public const SLOT_KIND = 'intercompany_request';

    public const SLOT_KEY = 'intercompany';

    public static function applies(Order $order): bool
    {
        if (! OrderOwnCompanySide::hasCarrierOwnCompanyColumn()) {
            return false;
        }

        $ownId = (int) ($order->own_company_id ?? 0);
        $carrierOwnId = (int) ($order->carrier_own_company_id ?? 0);

        return $ownId > 0 && $carrierOwnId > 0 && $ownId !== $carrierOwnId;
    }

    /**
     * Сумма межфирменной заявки/реализации (customer_rate × 0.95), 2 знака.
     */
    public static function amount(Order $order): ?string
    {
        $raw = $order->customer_rate;
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }

        $base = (float) $raw;
        if ($base <= 0) {
            return null;
        }

        return number_format(round($base * self::RATE_FACTOR, 2), 2, '.', '');
    }

    /**
     * @return array{
     *     key: string,
     *     label: string,
     *     description: string,
     *     party: string,
     *     accepted_types: list<string>,
     *     slot_kind: string,
     *     slot_key: string,
     *     contractor_id: int|null,
     *     order_leg_stage: string|null,
     *     counterparty_label: string|null,
     *     allows_multiple: bool,
     *     is_required: bool
     * }
     */
    public static function requirementSlot(?string $counterpartyLabel = null): array
    {
        return [
            'key' => self::SLOT_KIND.':'.self::SLOT_KEY,
            'label' => 'Межфирменная заявка',
            'description' => 'Заявка между своими компаниями (2-я рука → 1-я). Создаётся и подписывается при «Создать реализацию».',
            'party' => 'internal',
            'accepted_types' => OrderDocumentRequestEdoFulfillment::REQUEST_TYPES,
            'slot_kind' => self::SLOT_KIND,
            'slot_key' => self::SLOT_KEY,
            'contractor_id' => null,
            'order_leg_stage' => null,
            'counterparty_label' => $counterpartyLabel,
            'allows_multiple' => false,
            'is_required' => true,
        ];
    }
}
