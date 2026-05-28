<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\MarginCounterAmountResolver;
use App\Support\PaymentAmountVatConverter;
use App\Support\PaymentFormDictionary;
use App\Support\PaymentFormVat;

class SalesMarginCounterService
{
    public const DEFAULT_MIN_MARGIN_PERCENT = 10.0;

    public const ANCHOR_CUSTOMER_WITHOUT_VAT = 'customer_without_vat';

    public const ANCHOR_CUSTOMER_WITH_VAT = 'customer_with_vat';

    public const ANCHOR_CARRIER_WITHOUT_VAT = 'carrier_without_vat';

    public const ANCHOR_CARRIER_WITH_VAT = 'carrier_with_vat';

    /**
     * @var list<string>
     */
    public const ANCHOR_FIELDS = [
        self::ANCHOR_CUSTOMER_WITHOUT_VAT,
        self::ANCHOR_CUSTOMER_WITH_VAT,
        self::ANCHOR_CARRIER_WITHOUT_VAT,
        self::ANCHOR_CARRIER_WITH_VAT,
    ];

    public function __construct(
        private readonly OrderCompensationService $orderCompensationService,
        private readonly KpiConfigurationService $kpiConfigurationService,
        private readonly DealTypeClassifier $dealTypeClassifier,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $anchor = (string) ($input['anchor_field'] ?? '');
        $customerForm = filled($input['customer_payment_form'] ?? null)
            ? (string) $input['customer_payment_form']
            : PaymentFormDictionary::defaultClientVatCode();
        $carrierForm = filled($input['carrier_payment_form'] ?? null)
            ? (string) $input['carrier_payment_form']
            : 'no_vat';
        $minMarginPercent = max(0.0, (float) ($input['min_margin_percent'] ?? self::DEFAULT_MIN_MARGIN_PERCENT));
        $managerId = (int) ($input['manager_id'] ?? 0);
        $orderDate = $input['order_date'] ?? null;
        $additionalExpenses = (float) ($input['additional_expenses'] ?? 0);
        $insurance = (float) ($input['insurance'] ?? 0);
        $bonus = (float) ($input['bonus'] ?? 0);
        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();
        $fixedExpense = $additionalExpenses + $insurance + ($bonus * $bonusMultiplier);

        $customerWithout = $this->nullableAmount($input['customer_without_vat'] ?? null);
        $customerWith = $this->nullableAmount($input['customer_with_vat'] ?? null);
        $carrierWithout = $this->nullableAmount($input['carrier_without_vat'] ?? null);
        $carrierWith = $this->nullableAmount($input['carrier_with_vat'] ?? null);

        $customerNet = MarginCounterAmountResolver::customerRevenue(
            $anchor,
            $customerWithout,
            $customerWith,
            $customerForm,
            MarginCounterAmountResolver::BASIS_ORDER_NET,
        );
        $carrierNet = MarginCounterAmountResolver::carrierExpense(
            $anchor,
            $carrierWithout,
            $carrierWith,
            $carrierForm,
            MarginCounterAmountResolver::BASIS_ORDER_NET,
        );

        $formsDealType = $this->dealTypeClassifier->classify([
            'customer_payment_form' => $customerForm,
            'carrier_payment_form' => $carrierForm,
            'contractors_costs' => [['payment_form' => $carrierForm, 'amount' => $carrierNet ?? 0]],
        ]);

        $result = [
            'anchor_field' => $anchor !== '' ? $anchor : null,
            'min_margin_percent' => round($minMarginPercent, 2),
            'customer_payment_form' => $customerForm,
            'carrier_payment_form' => $carrierForm,
            'fixed_expense' => round($fixedExpense, 2),
            'forms_deal_type' => $formsDealType,
            'forms_deal_type_label' => $this->dealTypeLabel($formsDealType),
            'fields' => $this->emptyFields($customerForm, $carrierForm),
            'period_context' => null,
            'scenarios' => [],
            'summary' => [
                'scenario' => 'empty',
                'hints' => [],
            ],
        ];

        if ($managerId <= 0 || $orderDate === null) {
            $result['error'] = 'Укажите менеджера и дату (для KPI нужен текущий период).';

            return $result;
        }

        if ($formsDealType === 'unknown') {
            $result['error'] = 'Не удалось определить тип сделки по формам оплаты.';

            return $result;
        }

        $scenarioContext = [
            'anchor' => $anchor,
            'customer_without' => $customerWithout,
            'customer_with' => $customerWith,
            'carrier_without' => $carrierWithout,
            'carrier_with' => $carrierWith,
            'customer_form' => $customerForm,
            'carrier_form' => $carrierForm,
            'manager_id' => $managerId,
            'order_date' => $orderDate,
            'additional_expenses' => $additionalExpenses,
            'insurance' => $insurance,
            'bonus' => $bonus,
            'min_margin_percent' => $minMarginPercent,
            'fixed_expense' => $fixedExpense,
            'carrier_net' => $carrierNet,
        ];

        $result['scenarios'] = [
            $this->buildScenarioColumn('direct', 'direct', 'Прямая', $formsDealType, $scenarioContext, MarginCounterAmountResolver::BASIS_NEGOTIATION),
            $this->buildScenarioColumn('indirect', 'indirect', 'Кривая', $formsDealType, $scenarioContext, MarginCounterAmountResolver::BASIS_NEGOTIATION),
        ];

        if ($this->shouldShowOrderNetScenario($anchor, $customerWithout, $customerWith, $carrierWithout, $carrierWith, $customerForm, $carrierForm)) {
            $result['scenarios'][] = $this->buildScenarioColumn(
                'direct',
                'direct_order_net',
                'Прямая без НДС',
                $formsDealType,
                $scenarioContext,
                MarginCounterAmountResolver::BASIS_ORDER_NET,
            );
        }

        $firstScenario = $result['scenarios'][0] ?? null;
        if ($firstScenario !== null) {
            $result['period_context'] = [
                'orders_before' => $firstScenario['period_orders_before'],
                'direct_before' => $firstScenario['period_direct_before'],
            ];
        }

        $marginForFields = null;
        if ($customerNet !== null && $customerNet > 0 && $carrierNet !== null && $carrierNet >= 0) {
            $matching = collect($result['scenarios'])->firstWhere('matches_payment_forms', true);
            $marginForFields = $matching['margin'] ?? $result['scenarios'][0]['margin'] ?? null;
        }

        $result['fields'] = $this->buildFields($customerNet, $carrierNet, $marginForFields, $customerForm, $carrierForm);
        $result['summary'] = $this->buildSummary($customerNet, $carrierNet, $formsDealType, count($result['scenarios']) > 2);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildScenarioColumn(
        string $scenarioDealType,
        string $scenarioKey,
        string $scenarioLabel,
        string $formsDealType,
        array $context,
        string $amountBasis,
    ): array {
        $anchor = (string) $context['anchor'];
        $customerForm = (string) $context['customer_form'];
        $carrierForm = (string) $context['carrier_form'];
        $customerWithout = $context['customer_without'];
        $customerWith = $context['customer_with'];
        $carrierWithout = $context['carrier_without'];
        $carrierWith = $context['carrier_with'];
        $minMarginPercent = (float) $context['min_margin_percent'];
        $fixedExpense = (float) $context['fixed_expense'];
        $carrierNet = $context['carrier_net'];

        $customerRevenue = MarginCounterAmountResolver::customerRevenue(
            $anchor,
            $customerWithout,
            $customerWith,
            $customerForm,
            $amountBasis,
        );
        $carrierExpense = MarginCounterAmountResolver::carrierExpense(
            $anchor,
            $carrierWithout,
            $carrierWith,
            $carrierForm,
            $amountBasis,
        );

        $compensationPayload = [
            'customer_rate' => max(0.0, (float) ($customerRevenue ?? 0)),
            'carrier_rate' => max(0.0, (float) ($carrierExpense ?? 0)),
            'additional_expenses' => (float) $context['additional_expenses'],
            'insurance' => (float) $context['insurance'],
            'bonus' => (float) $context['bonus'],
            'manager_id' => (int) $context['manager_id'],
            'order_date' => $context['order_date'],
            'customer_payment_form' => $customerForm,
            'carrier_payment_form' => $carrierForm,
            'contractors_costs' => [['payment_form' => $carrierForm, 'amount' => max(0.0, (float) ($carrierNet ?? 0))]],
        ];

        $evaluation = $this->orderCompensationService->calculateMarginScenario($compensationPayload, $scenarioDealType);
        $kpiPercent = (float) $evaluation['kpi_percent'];

        $matchesForms = $amountBasis === MarginCounterAmountResolver::BASIS_NEGOTIATION
            && $formsDealType === $scenarioDealType;

        $column = [
            'scenario_key' => $scenarioKey,
            'deal_type' => $scenarioDealType,
            'deal_type_label' => $scenarioLabel,
            'amount_basis' => $amountBasis,
            'matches_payment_forms' => $matchesForms,
            'kpi_percent' => $kpiPercent,
            'projected_direct_ratio_percent' => round((float) $evaluation['projected_direct_ratio'] * 100, 1),
            'period_orders_before' => (int) $evaluation['period_orders_before'],
            'period_direct_before' => (int) $evaluation['period_direct_before'],
            'period_orders_after' => (int) $evaluation['period_orders_after'],
            'period_direct_after' => (int) $evaluation['period_direct_after'],
            'period_note' => $this->periodNote($evaluation, $scenarioLabel),
            'margin' => null,
            'margin_percent' => null,
            'margin_quality' => null,
            'margin_quality_label' => null,
            'salary_accrued' => null,
            'max_carrier_without_vat' => null,
            'max_carrier_with_vat' => null,
            'min_customer_without_vat' => null,
            'min_customer_with_vat' => null,
        ];

        if ($customerRevenue !== null && $customerRevenue > 0 && $carrierExpense !== null && $carrierExpense >= 0) {
            $this->applyMarginMetrics(
                $column,
                (float) $evaluation['delta'],
                $customerRevenue,
                $minMarginPercent,
                (float) $evaluation['salary_accrued'],
            );
        } elseif ($customerRevenue !== null && $customerRevenue > 0 && $carrierExpense === null) {
            $targetMargin = $customerRevenue * ($minMarginPercent / 100);
            $maxCarrier = $this->deriveCarrierExpense($customerRevenue, $targetMargin, $kpiPercent, $fixedExpense);
            $pair = PaymentAmountVatConverter::pairFromNet(max(0.0, $maxCarrier), $carrierForm);
            $column['max_carrier_without_vat'] = $pair['without_vat'];
            $column['max_carrier_with_vat'] = $pair['with_vat'];
        } elseif ($carrierExpense !== null && $carrierExpense > 0 && ($customerRevenue === null || $customerRevenue <= 0)) {
            $minCustomer = $this->suggestCustomerRevenue($carrierExpense + $fixedExpense, $kpiPercent, $minMarginPercent);
            $pair = PaymentAmountVatConverter::pairFromNet($minCustomer, $customerForm);
            $column['min_customer_without_vat'] = $pair['without_vat'];
            $column['min_customer_with_vat'] = $pair['with_vat'];
        }

        return $column;
    }

    private function shouldShowOrderNetScenario(
        string $anchor,
        ?float $customerWithout,
        ?float $customerWith,
        ?float $carrierWithout,
        ?float $carrierWith,
        string $customerForm,
        string $carrierForm,
    ): bool {
        if (! PaymentFormVat::isVatCode($customerForm) && ! PaymentFormVat::isVatCode($carrierForm)) {
            return false;
        }

        $negotiationCustomer = MarginCounterAmountResolver::customerRevenue(
            $anchor,
            $customerWithout,
            $customerWith,
            $customerForm,
            MarginCounterAmountResolver::BASIS_NEGOTIATION,
        );
        $orderNetCustomer = MarginCounterAmountResolver::customerRevenue(
            $anchor,
            $customerWithout,
            $customerWith,
            $customerForm,
            MarginCounterAmountResolver::BASIS_ORDER_NET,
        );

        if ($negotiationCustomer !== null && $orderNetCustomer !== null
            && abs($negotiationCustomer - $orderNetCustomer) > 0.009) {
            return true;
        }

        $negotiationCarrier = MarginCounterAmountResolver::carrierExpense(
            $anchor,
            $carrierWithout,
            $carrierWith,
            $carrierForm,
            MarginCounterAmountResolver::BASIS_NEGOTIATION,
        );
        $orderNetCarrier = MarginCounterAmountResolver::carrierExpense(
            $anchor,
            $carrierWithout,
            $carrierWith,
            $carrierForm,
            MarginCounterAmountResolver::BASIS_ORDER_NET,
        );

        return $negotiationCarrier !== null && $orderNetCarrier !== null
            && abs($negotiationCarrier - $orderNetCarrier) > 0.009;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    private function applyMarginMetrics(array &$column, float $delta, float $customerRevenue, float $minMarginPercent, float $salaryAccrued): void
    {
        $marginPercent = $customerRevenue > 0 ? ($delta / $customerRevenue) * 100 : 0.0;
        $quality = $this->marginQuality($marginPercent, $minMarginPercent);

        $column['margin'] = round($delta, 2);
        $column['margin_percent'] = round($marginPercent, 2);
        $column['margin_quality'] = $quality;
        $column['margin_quality_label'] = $this->marginQualityLabel($quality);
        $column['salary_accrued'] = round($salaryAccrued, 2);
    }

    /**
     * @param  array<string, mixed>  $evaluation
     */
    private function periodNote(array $evaluation, string $scenarioLabel): string
    {
        $beforeTotal = (int) $evaluation['period_orders_before'];
        $beforeDirect = (int) $evaluation['period_direct_before'];
        $afterTotal = (int) $evaluation['period_orders_after'];
        $afterDirect = (int) $evaluation['period_direct_after'];
        $ratioPercent = round((float) $evaluation['projected_direct_ratio'] * 100, 1);

        if ($beforeTotal === 0) {
            return sprintf(
                'В периоде пока нет сделок; если эта заявка — %s: KPI %s%%.',
                mb_strtolower($scenarioLabel),
                $this->formatPercent((float) ($evaluation['kpi_percent'] ?? 0)),
            );
        }

        return sprintf(
            'В периоде %d (%d прямых); с заявкой как %s: %d (%d прямых, %s%% прямых) → KPI %s%%.',
            $beforeTotal,
            $beforeDirect,
            mb_strtolower($scenarioLabel),
            $afterTotal,
            $afterDirect,
            $this->formatPercent($ratioPercent),
            $this->formatPercent((float) ($evaluation['kpi_percent'] ?? 0)),
        );
    }

    /**
     * @return array{scenario: string, hints: list<string>}
     */
    private function buildSummary(?float $customerNet, ?float $carrierNet, string $formsDealType, bool $hasOrderNetColumn): array
    {
        $hints = [
            sprintf('По формам оплаты сделка: %s.', $this->dealTypeLabel($formsDealType)),
            $hasOrderNetColumn
                ? 'Столбцы «Прямая» и «Кривая» — по введённым суммам; «Прямая без НДС» — как в карточке заказа (ставки без НДС).'
                : 'Два варианта KPI, если заявку учесть в периоде как прямую или как кривую (суммы как введены в полях).',
        ];

        if ($customerNet !== null && $customerNet > 0 && $carrierNet !== null) {
            return ['scenario' => 'both_rates', 'hints' => $hints];
        }

        if ($customerNet !== null && $customerNet > 0) {
            $hints[] = 'Указана только ставка заказчика — в каждом столбце максимум перевозчику при заданном пороге маржи.';

            return ['scenario' => 'customer_only', 'hints' => $hints];
        }

        if ($carrierNet !== null && $carrierNet > 0) {
            $hints[] = 'Указана только ставка перевозчика — в каждом столбце минимум заказчику при заданном пороге маржи.';

            return ['scenario' => 'carrier_only', 'hints' => $hints];
        }

        $hints[] = 'Укажите ставки заказчика и перевозчика.';

        return ['scenario' => 'empty', 'hints' => $hints];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFields(
        ?float $customerNet,
        ?float $carrierNet,
        ?float $margin,
        string $customerForm,
        string $carrierForm,
    ): array {
        $customerPair = PaymentAmountVatConverter::pairFromNet($customerNet, $customerForm);
        $carrierPair = PaymentAmountVatConverter::pairFromNet($carrierNet, $carrierForm);

        return [
            'customer_without_vat' => $customerPair['without_vat'],
            'customer_with_vat' => $customerPair['with_vat'],
            'customer_vat_rate_percent' => $customerPair['vat_rate_percent'],
            'carrier_without_vat' => $carrierPair['without_vat'],
            'carrier_with_vat' => $carrierPair['with_vat'],
            'carrier_vat_rate_percent' => $carrierPair['vat_rate_percent'],
            'margin' => $margin !== null ? round($margin, 2) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFields(string $customerForm, string $carrierForm): array
    {
        return [
            'customer_without_vat' => null,
            'customer_with_vat' => null,
            'customer_vat_rate_percent' => PaymentAmountVatConverter::presentationRatePercent($customerForm),
            'carrier_without_vat' => null,
            'carrier_with_vat' => null,
            'carrier_vat_rate_percent' => PaymentAmountVatConverter::presentationRatePercent($carrierForm),
            'margin' => null,
        ];
    }

    private function deriveCarrierExpense(
        float $customerRevenue,
        float $margin,
        float $kpiPercent,
        float $fixedExpense,
    ): float {
        return round(max(0.0, $customerRevenue - ($customerRevenue * ($kpiPercent / 100)) - $fixedExpense - $margin), 2);
    }

    private function suggestCustomerRevenue(
        float $totalExpense,
        float $kpiPercent,
        float $minMarginPercent,
    ): float {
        if ($totalExpense <= 0) {
            return 0.0;
        }

        $denominator = 1 - ($kpiPercent / 100) - ($minMarginPercent / 100);

        if ($denominator <= 0) {
            return 0.0;
        }

        return $totalExpense / $denominator;
    }

    private function marginQuality(float $marginPercent, float $minMarginPercent): string
    {
        if ($marginPercent < $minMarginPercent) {
            return 'below_minimum';
        }

        if ($marginPercent < $minMarginPercent + 5) {
            return 'acceptable';
        }

        return 'good';
    }

    private function marginQualityLabel(string $quality): string
    {
        return match ($quality) {
            'below_minimum' => 'Ниже порога',
            'acceptable' => 'На пороге',
            default => 'Хорошая маржа',
        };
    }

    private function dealTypeLabel(string $dealType): string
    {
        return match ($dealType) {
            'direct' => 'Прямая',
            'indirect' => 'Кривая',
            default => 'Не определена',
        };
    }

    private function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
