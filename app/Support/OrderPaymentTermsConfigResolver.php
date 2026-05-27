<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\FinancialTerm;
use App\Models\Order;
use Illuminate\Support\Facades\Schema;

/**
 * Единый источник JSON условий оплаты для синхронизации графика и UI.
 *
 * График перевозчиков в payment_schedules строится из financial_terms.contractors_costs;
 * график заказчика исторически читался только из orders.payment_terms и часто оказывался пустым,
 * хотя актуальные условия лежат в payment_terms_snapshot или wizard_state.
 */
final class OrderPaymentTermsConfigResolver
{
    /**
     * @return array<string, mixed>
     */
    public static function forSync(Order $order): array
    {
        $config = self::decodeJson($order->getAttribute('payment_terms'));
        $client = is_array($config['client'] ?? null) ? $config['client'] : [];
        $client['payment_schedule'] = self::resolveClientPaymentSchedule($order);

        if (! isset($client['payment_form']) && filled($order->customer_payment_form)) {
            $client['payment_form'] = $order->customer_payment_form;
        }

        if (! isset($client['request_mode'])) {
            $client['request_mode'] = 'single_request';
        }

        $config['client'] = $client;

        return $config;
    }

    /**
     * Самый детальный график заказчика из orders.payment_terms, snapshot и wizard_state.
     *
     * @return array<string, mixed>
     */
    public static function resolveClientPaymentSchedule(Order $order): array
    {
        /** @var list<array<string, mixed>> $candidates */
        $candidates = [];

        $fromOrder = data_get(self::decodeJson($order->getAttribute('payment_terms')), 'client.payment_schedule', []);
        if (is_array($fromOrder)) {
            $candidates[] = $fromOrder;
        }

        foreach (self::alternateConfigs($order) as $config) {
            $schedule = data_get($config, 'client.payment_schedule', []);
            if (is_array($schedule)) {
                $candidates[] = $schedule;
            }
        }

        return PaymentScheduleStructure::pickRichestSchedule($candidates);
    }

    /**
     * @return array<string, mixed>
     */
    private static function decodeJson(mixed $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function alternateConfigs(Order $order): array
    {
        return [
            ...self::configsFromFinancialTermSnapshot($order),
            ...self::configsFromWizardState($order),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function configsFromFinancialTermSnapshot(Order $order): array
    {
        if (! $order->exists) {
            return [];
        }

        try {
            if (! Schema::hasTable('financial_terms') || ! Schema::hasColumn('financial_terms', 'payment_terms_snapshot')) {
                return [];
            }

            $financialTerm = $order->relationLoaded('financialTerms')
                ? $order->financialTerms->first()
                : FinancialTerm::query()->where('order_id', $order->id)->first();

            if ($financialTerm === null) {
                return [];
            }

            $snapshot = self::decodeJson($financialTerm->payment_terms_snapshot);

            return $snapshot !== [] ? [$snapshot] : [];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function configsFromWizardState(Order $order): array
    {
        $wizardState = $order->wizard_state;
        if (! is_array($wizardState)) {
            return [];
        }

        $financialTerm = $wizardState['financial_term'] ?? null;
        if (! is_array($financialTerm)) {
            return [];
        }

        return [
            [
                'client' => [
                    'payment_form' => $financialTerm['client_payment_form'] ?? $order->customer_payment_form,
                    'request_mode' => $financialTerm['client_request_mode'] ?? 'single_request',
                    'payment_schedule' => $financialTerm['client_payment_schedule'] ?? [],
                    'payment_terms_text' => $financialTerm['client_payment_terms'] ?? null,
                ],
            ],
        ];
    }
}
