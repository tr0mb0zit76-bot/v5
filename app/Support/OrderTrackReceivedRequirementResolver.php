<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FinancialTerm;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use JsonException;

/**
 * Нужна ли ручная «дата получения» (квиток / оригиналы) по стороне и пакету документов.
 */
final class OrderTrackReceivedRequirementResolver
{
    /**
     * @param  array<string, mixed>  $schedule
     */
    public static function scheduleNeedsTrackReceived(array $schedule): bool
    {
        $kinds = self::scheduleTrackPackageKinds($schedule);

        return $kinds['request'] || $kinds['closing'];
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return array{request: bool, closing: bool}
     */
    public static function scheduleTrackPackageKinds(array $schedule): array
    {
        $normalized = PaymentInstallmentScheduleNormalizer::ensureInstallmentModel($schedule);
        $installments = $normalized['installments'] ?? [];
        $kinds = ['request' => false, 'closing' => false];

        if (! is_array($installments)) {
            return $kinds;
        }

        foreach ($installments as $row) {
            if (! is_array($row)) {
                continue;
            }

            $basis = strtolower(trim((string) ($row['basis'] ?? '')));

            if ($basis === 'ottn') {
                $kinds['request'] = true;
            }

            if ($basis === 'fttn_receipt') {
                $kinds['closing'] = true;
            }
        }

        return $kinds;
    }

    public static function orderNeedsCustomerTrackReceived(Order $order, ?FinancialTerm $financialTerm = null): bool
    {
        $kinds = self::customerPackageKinds($order, $financialTerm);

        return $kinds['request'] || $kinds['closing'];
    }

    public static function orderNeedsCarrierTrackReceived(Order $order, ?FinancialTerm $financialTerm = null): bool
    {
        $kinds = self::carrierPackageKinds($order, $financialTerm);

        return $kinds['request'] || $kinds['closing'];
    }

    /**
     * @return array{request: bool, closing: bool}
     */
    public static function customerPackageKinds(Order $order, ?FinancialTerm $financialTerm = null): array
    {
        return self::scheduleTrackPackageKinds(self::resolveClientPaymentSchedule($order, $financialTerm));
    }

    /**
     * @return array{request: bool, closing: bool}
     */
    public static function carrierPackageKinds(Order $order, ?FinancialTerm $financialTerm = null): array
    {
        $merged = ['request' => false, 'closing' => false];

        foreach (self::resolveContractorsCosts($order, $financialTerm) as $cost) {
            if (! is_array($cost)) {
                continue;
            }

            // Нал перевозчику: срок от ТСД, ручная дата получения не нужна.
            if (PaymentScheduleCashBasis::isCash(isset($cost['payment_form']) ? (string) $cost['payment_form'] : null)) {
                continue;
            }

            $kinds = self::scheduleTrackPackageKinds((array) ($cost['payment_schedule'] ?? []));
            $merged['request'] = $merged['request'] || $kinds['request'];
            $merged['closing'] = $merged['closing'] || $kinds['closing'];
        }

        return $merged;
    }

    /**
     * @return array{
     *     needs_track_received_date_customer: bool,
     *     needs_track_received_date_carrier: bool,
     *     needs_track_received_date_customer_request: bool,
     *     needs_track_received_date_customer_closing: bool,
     *     needs_track_received_date_carrier_request: bool,
     *     needs_track_received_date_carrier_closing: bool,
     * }
     */
    public static function flagsForOrder(Order $order, ?FinancialTerm $financialTerm = null): array
    {
        $customer = self::customerPackageKinds($order, $financialTerm);
        $carrier = self::carrierPackageKinds($order, $financialTerm);

        if (DocumentRegistryGridColumnApplicabilityResolver::orderIsOwnFleetCarrierOnly($order)) {
            $carrier = ['request' => false, 'closing' => false];
        }

        return [
            'needs_track_received_date_customer' => $customer['request'] || $customer['closing'],
            'needs_track_received_date_carrier' => $carrier['request'] || $carrier['closing'],
            // Редактирование пакетов: если стороне нужна любая «дата получения»,
            // даём отдельно заявку и закрывающие (не только при fttn_receipt / ottn).
            'needs_track_received_date_customer_request' => $customer['request'] || $customer['closing'],
            'needs_track_received_date_customer_closing' => $customer['request'] || $customer['closing'],
            'needs_track_received_date_carrier_request' => $carrier['request'] || $carrier['closing'],
            'needs_track_received_date_carrier_closing' => $carrier['request'] || $carrier['closing'],
        ];
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return array<int, array{
     *     needs_track_received_date_customer: bool,
     *     needs_track_received_date_carrier: bool,
     *     needs_track_received_date_customer_request: bool,
     *     needs_track_received_date_customer_closing: bool,
     *     needs_track_received_date_carrier_request: bool,
     *     needs_track_received_date_carrier_closing: bool,
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

    public static function fieldIsRequiredForOrder(Order $order, string $field, ?FinancialTerm $financialTerm = null): bool
    {
        $flags = self::flagsForOrder($order, $financialTerm);

        return match ($field) {
            OrderTrackReceivedFields::CUSTOMER_REQUEST,
            OrderTrackReceivedFields::CUSTOMER_CLOSING,
            OrderTrackReceivedFields::CUSTOMER_LEGACY => (bool) ($flags['needs_track_received_date_customer'] ?? false),
            OrderTrackReceivedFields::CARRIER_REQUEST,
            OrderTrackReceivedFields::CARRIER_CLOSING,
            OrderTrackReceivedFields::CARRIER_LEGACY => (bool) ($flags['needs_track_received_date_carrier'] ?? false),
            default => false,
        };
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
