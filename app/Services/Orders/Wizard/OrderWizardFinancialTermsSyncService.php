<?php

namespace App\Services\Orders\Wizard;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Support\CarrierPaymentTermResolver;
use App\Support\CashToCashMarginCalculator;
use App\Support\OrderPaymentTermsConfigResolver;
use Illuminate\Support\Facades\Schema;
use JsonException;

class OrderWizardFinancialTermsSyncService
{
    public function __construct(
        private readonly OrderWizardPerformersPayloadBuilder $performersPayloadBuilder,
        private readonly OrderWizardContractorsCostsNormalizer $contractorsCostsNormalizer,
    ) {}

    /**
     * Таблица заказов хранит итоговые ставки в `orders`; карточка подгружает детализацию из `financial_terms`.
     * После inline-редактирования ставок в гриде синхронизируем строку финансовых условий, чтобы не расходились данные.
     */
    public function syncFromOrderRates(Order $order): void
    {
        if (! Schema::hasTable('financial_terms')) {
            return;
        }

        $orderId = (int) $order->getKey();
        if ($orderId <= 0) {
            return;
        }

        $financialTerm = FinancialTerm::query()->where('order_id', $orderId)->first();

        if ($financialTerm === null) {
            $serializedPerformers = $this->performersPayloadBuilder->build($order, null);
            $attributes = [
                'order_id' => $orderId,
                'client_price' => $order->customer_rate,
                'client_currency' => 'RUB',
                'contractors_costs' => $this->contractorsCostsNormalizer->normalize($order, null, $serializedPerformers),
                'total_cost' => 0,
                'margin' => 0,
                'additional_costs' => [],
            ];

            if (Schema::hasColumn('financial_terms', 'client_payment_terms')) {
                $attributes['client_payment_terms'] = $order->customer_payment_term;
            }

            $financialTerm = $order->financialTerms()->create(
                collect($attributes)->except('order_id')->all(),
            );
        }

        if ($order->customer_rate !== null) {
            $financialTerm->client_price = $order->customer_rate;
        }

        $serializedPerformers = $this->performersPayloadBuilder->build($order, $financialTerm);
        $costs = $this->contractorsCostsNormalizer->normalize($order, $financialTerm, $serializedPerformers);
        $costs = $this->applyOrderCarrierPaymentFormToSyncedCosts($order, $costs);
        $financialTerm->contractors_costs = $costs;

        $contractorsSum = collect($costs)->sum(fn (array $c): float => (float) ($c['amount'] ?? 0));
        $additionalTotal = collect($financialTerm->additional_costs ?? [])
            ->sum(fn (array $row): float => (float) ($row['amount'] ?? 0));
        $financialTerm->total_cost = $contractorsSum + $additionalTotal;

        $kpiPercent = (float) ($order->kpi_percent ?? 0);
        $clientPrice = (float) ($order->customer_rate ?? $financialTerm->client_price ?? 0);
        $cashToCash = CashToCashMarginCalculator::isCashToCash(
            (string) ($order->customer_payment_form ?? ''),
            $costs,
        );
        $financialTerm->margin = CashToCashMarginCalculator::margin(
            $clientPrice,
            (float) $financialTerm->total_cost,
            $kpiPercent,
            $cashToCash,
        );

        $order->refresh();

        $mergedPaymentTerms = $this->mergeOrderPaymentTermsCarriersIntoJson($order, $costs);
        if (Schema::hasColumn('financial_terms', 'payment_terms_snapshot') && $mergedPaymentTerms !== null) {
            $financialTerm->payment_terms_snapshot = $mergedPaymentTerms;
        }

        $financialTerm->save();

        $fill = [];
        if (Schema::hasColumn('orders', 'carrier_payment_term')) {
            $term = CarrierPaymentTermResolver::fromContractorsCostsArray($costs);
            if ($term !== null) {
                $fill['carrier_payment_term'] = $term;
            }
        }
        if ($mergedPaymentTerms !== null && Schema::hasColumn('orders', 'payment_terms')) {
            $fill['payment_terms'] = $mergedPaymentTerms;
        }
        if ($fill !== []) {
            $order->forceFill($fill)->saveQuietly();
        }
    }

    /**
     * Обновляет блок `carriers` в JSON `orders.payment_terms`, сохраняя `client` при наличии.
     *
     * @param  list<array<string, mixed>>  $contractorsCosts
     */
    private function mergeOrderPaymentTermsCarriersIntoJson(Order $order, array $contractorsCosts): ?string
    {
        if (! Schema::hasColumn('orders', 'payment_terms')) {
            return null;
        }

        try {
            $config = OrderPaymentTermsConfigResolver::forSync($order);

            if (! isset($config['client']) || ! is_array($config['client'])) {
                $config['client'] = [
                    'payment_form' => $order->customer_payment_form,
                    'request_mode' => 'single_request',
                    'payment_schedule' => [],
                ];
            }

            $config['carriers'] = collect($contractorsCosts)
                ->map(function (array $c): array {
                    $schedule = $c['payment_schedule'] ?? [];
                    if (! is_array($schedule)) {
                        $schedule = [];
                    }

                    return [
                        'stage' => $c['stage'] ?? null,
                        'contractor_id' => isset($c['contractor_id']) && $c['contractor_id'] !== null ? (int) $c['contractor_id'] : null,
                        'payment_form' => $c['payment_form'] ?? null,
                        'payment_schedule' => $schedule,
                    ];
                })
                ->values()
                ->all();

            return json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * После инлайна в гриде `orders.carrier_payment_form` — источник правды для одной строки затрат (одно плечо).
     *
     * @param  list<array<string, mixed>>  $costs
     * @return list<array<string, mixed>>
     */
    private function applyOrderCarrierPaymentFormToSyncedCosts(Order $order, array $costs): array
    {
        $form = $order->carrier_payment_form;
        if ($form === null || $form === '' || $form === 'mixed') {
            return $costs;
        }

        if (count($costs) !== 1) {
            return $costs;
        }

        $costs[0]['payment_form'] = $form;

        return $costs;
    }
}
