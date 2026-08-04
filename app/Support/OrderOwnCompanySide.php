<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\PrintFormBasicTerm;
use App\Models\PrintFormTemplate;
use App\Models\User;
use App\Services\PrintFormTemplateOrderEligibility;
use Illuminate\Support\Facades\Schema;

/**
 * «Наша сторона» в печати: генподрядчик (заказчик) vs субподрядчик (перевозчик).
 *
 * Без субподряда carrier_own_company_id пуст — обе стороны берут own_company_id.
 */
final class OrderOwnCompanySide
{
    public static function idForPrintParty(Order $order, ?string $party): ?int
    {
        if (
            $party === PrintFormBasicTerm::PARTY_CARRIER
            && self::hasCarrierOwnCompanyColumn()
            && $order->carrier_own_company_id !== null
            && (int) $order->carrier_own_company_id > 0
        ) {
            return (int) $order->carrier_own_company_id;
        }

        return $order->own_company_id !== null ? (int) $order->own_company_id : null;
    }

    public static function contractorForPrintParty(Order $order, ?string $party): ?Contractor
    {
        $id = self::idForPrintParty($order, $party);
        if ($id === null || $id <= 0) {
            return null;
        }

        if (
            $party === PrintFormBasicTerm::PARTY_CARRIER
            && self::hasCarrierOwnCompanyColumn()
            && (int) ($order->carrier_own_company_id ?? 0) === $id
            && $order->relationLoaded('carrierOwnCompany')
        ) {
            return $order->carrierOwnCompany;
        }

        if ((int) ($order->own_company_id ?? 0) === $id && $order->relationLoaded('ownCompany')) {
            return $order->ownCompany;
        }

        return Contractor::query()->find($id);
    }

    /**
     * Р/с для lp_* : для субподрядчика пока основной счёт компании (отдельного поля нет).
     */
    public static function bankAccountIdForPrintParty(Order $order, ?string $party): mixed
    {
        $ownId = self::idForPrintParty($order, $party);
        if ($ownId === null) {
            return null;
        }

        if (
            $party === PrintFormBasicTerm::PARTY_CARRIER
            && self::hasCarrierOwnCompanyColumn()
            && (int) ($order->carrier_own_company_id ?? 0) === $ownId
            && (int) ($order->own_company_id ?? 0) !== $ownId
        ) {
            return null;
        }

        return $order->own_company_bank_account_id;
    }

    /**
     * @return list<int>
     */
    public static function allOwnCompanyIds(Order $order): array
    {
        $ids = [];

        if ($order->own_company_id !== null && (int) $order->own_company_id > 0) {
            $ids[] = (int) $order->own_company_id;
        }

        if (
            self::hasCarrierOwnCompanyColumn()
            && $order->carrier_own_company_id !== null
            && (int) $order->carrier_own_company_id > 0
        ) {
            $ids[] = (int) $order->carrier_own_company_id;
        }

        return array_values(array_unique($ids));
    }

    public static function userCanSignAnySide(User $user, Order $order): bool
    {
        $ids = self::allOwnCompanyIds($order);

        if ($ids === []) {
            return $user->canSignDocumentsForOwnCompany(null);
        }

        foreach ($ids as $id) {
            if ($user->canSignDocumentsForOwnCompany($id)) {
                return true;
            }
        }

        return false;
    }

    public static function userCanSignForParty(User $user, Order $order, ?string $party): bool
    {
        return $user->canSignDocumentsForOwnCompany(self::idForPrintParty($order, $party));
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function partyFromDocument(?array $metadata, ?PrintFormTemplate $template = null): ?string
    {
        $fromMeta = is_array($metadata) ? ($metadata['party'] ?? null) : null;
        if (is_string($fromMeta) && in_array($fromMeta, [
            PrintFormBasicTerm::PARTY_CUSTOMER,
            PrintFormBasicTerm::PARTY_CARRIER,
        ], true)) {
            return $fromMeta;
        }

        $fromTemplate = $template?->party;
        if (is_string($fromTemplate) && in_array($fromTemplate, [
            PrintFormBasicTerm::PARTY_CUSTOMER,
            PrintFormBasicTerm::PARTY_CARRIER,
        ], true)) {
            return $fromTemplate;
        }

        if ($template instanceof PrintFormTemplate) {
            $eligibility = app(PrintFormTemplateOrderEligibility::class);
            $effective = $eligibility->effectivePrintParty($eligibility->templateToArray($template));
            if (in_array($effective, [
                PrintFormBasicTerm::PARTY_CUSTOMER,
                PrintFormBasicTerm::PARTY_CARRIER,
            ], true)) {
                return $effective;
            }
        }

        return null;
    }

    public static function hasCarrierOwnCompanyColumn(): bool
    {
        return Schema::hasColumn('orders', 'carrier_own_company_id');
    }
}
