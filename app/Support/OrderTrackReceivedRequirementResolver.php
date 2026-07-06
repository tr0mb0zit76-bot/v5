<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FinancialTerm;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use JsonException;

/**
 * Нужна ли ручная «дата получения» (квиток / оригиналы) по стороне заказа.
 */
final class OrderTrackReceivedRequirementResolver
{
    private const TRACK_RECEIVED_BASES = ['ottn', 'fttn_receipt'];

    /**
     * @param  array<string, mixed>  $schedule
     */
    public static function scheduleNeedsTrackReceived(array $schedule): bool
    {
        $normalized = PaymentInstallmentScheduleNormalizer::ensureInstallmentModel($schedule);
        $installments = $normalized['installments'] ?? [];

        if (! is_array($installments)) {
            return false;
        }

        foreach ($installments as $row) {
            if (! is_array($row)) {
                continue;
            }

            $basis = strtolower(trim((string) ($row['basis'] ?? '')));

            if (in_array($basis, self::TRACK_RECEIVED_BASES, true)) {
                return true;
            }
        }

        return false;
    }

    public static function orderNeedsCustomerTrackReceived(Order $order, ?FinancialTerm $financialTerm = null): bool
    {
        $schedule = self::resolveClientPaymentSchedule($order, $financialTerm);

        return self::scheduleNeedsTrackReceived($schedule);
    }

    public static function orderNeedsCarrierTrackReceived(Order $order, ?FinancialTerm $financialTerm = null): bool
    {
        foreach (self::resolveContractorsCosts($order, $financialTerm) as $cost) {
            if (! is_array($cost)) {
                continue;
            }

            $schedule = (array) ($cost['payment_schedule'] ?? []);

            if (self::scheduleNeedsTrackReceived($schedule)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     needs_track_received_date_customer: bool,
     *     needs_track_received_date_carrier: bool,
     * }
     */
    public static function flagsForOrder(Order $order, ?FinancialTerm $financialTerm = null): array
    {
        $flags = [
            'needs_track_received_date_customer' => self::orderNeedsCustomerTrackReceived($order, $financialTerm),
            'needs_track_received_date_carrier' => self::orderNeedsCarrierTrackReceived($order, $financialTerm),
        ];

        if (DocumentRegistryGridColumnApplicabilityResolver::orderIsOwnFleetCarrierOnly($order)) {
            $flags['needs_track_received_date_carrier'] = false;
        }

        return $flags;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array{
     *     needs_track_received_date_customer: bool,
     *     needs_track_received_date_carrier: bool,
     * }>
     */
    public static function mapFlagsForOrders(Collection $orders): array
    {
        if ($orders->isEmpty()) {
            return [];
        }

        $financialTermsByOrderId = self::financialTermsByOrderId($orders);

        $map = [];

        foreach ($orders as $order) {
            $orderId = (int) $order->id;
            $map[$orderId] = self::flagsForOrder($order, $financialTermsByOrderId->get($orderId));
        }

        return $map;
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return Collection<int, FinancialTerm>
     */
    private static function financialTermsByOrderId(Collection $orders): Collection
    {
        if (! Schema::hasTable('financial_terms')) {
            return collect();
        }

        $orderIds = $orders
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($orderIds === []) {
            return collect();
        }

        return FinancialTerm::query()
            ->whereIn('order_id', $orderIds)
            ->orderByDesc('id')
            ->get()
            ->unique('order_id')
            ->keyBy(static fn (FinancialTerm $row): int => (int) $row->order_id);
    }

    /**
     * @return array<string, mixed>
     */
    private static function resolveClientPaymentSchedule(Order $order, ?FinancialTerm $financialTerm): array
    {
        $wizardSchedule = data_get($order->wizard_state, 'financial_term.client_payment_schedule');

        if (is_array($wizardSchedule) && $wizardSchedule !== []) {
            return $wizardSchedule;
        }

        $paymentTerms = self::decodePaymentTermsConfig($order, $financialTerm);
        $fromTerms = data_get($paymentTerms, 'client.payment_schedule');

        return is_array($fromTerms) ? $fromTerms : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function resolveContractorsCosts(Order $order, ?FinancialTerm $financialTerm): array
    {
        $wizardCosts = data_get($order->wizard_state, 'financial_term.contractors_costs');

        if (is_array($wizardCosts) && $wizardCosts !== []) {
            return array_values(array_filter($wizardCosts, static fn (mixed $row): bool => is_array($row)));
        }

        $costs = $financialTerm?->contractors_costs;

        return is_array($costs) ? array_values(array_filter($costs, static fn (mixed $row): bool => is_array($row))) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodePaymentTermsConfig(Order $order, ?FinancialTerm $financialTerm): array
    {
        $raw = $order->getAttribute('payment_terms');

        if (($raw === null || $raw === '') && $financialTerm !== null) {
            $raw = $financialTerm->payment_terms_snapshot;
        }

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        try {
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (JsonException) {
            return [];
        }
    }
}
