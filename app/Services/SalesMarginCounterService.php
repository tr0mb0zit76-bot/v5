<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PaymentFormDictionary;

class SalesMarginCounterService
{
    public const SCENARIO_CASH = 'cash';

    public const SCENARIO_VAT = 'vat';

    public function __construct(
        private readonly OrderCompensationService $orderCompensationService,
        private readonly KpiConfigurationService $kpiConfigurationService,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function calculate(array $input): array
    {
        $managerId = (int) ($input['manager_id'] ?? 0);
        $orderDate = $input['order_date'] ?? null;
        $additionalExpenses = max(0.0, (float) ($input['additional_expenses'] ?? 0));
        $insurance = max(0.0, (float) ($input['insurance'] ?? 0));
        $bonus = max(0.0, (float) ($input['bonus'] ?? 0));
        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();
        $fixedExpense = $additionalExpenses + $insurance + ($bonus * $bonusMultiplier);

        $customerRate = $this->resolveCustomerRate($input);
        $carrierCash = $this->nullableAmount($input['carrier_cash_rate'] ?? null);
        $carrierCashless = $this->nullableAmount($input['carrier_cashless_rate'] ?? null);

        $defaultVatForm = PaymentFormDictionary::defaultClientVatCode();
        $deductionRates = $this->kpiConfigurationService->deductionRates();

        $result = [
            'fixed_expense' => round($fixedExpense, 2),
            'kpi_settings' => [
                'deduction_rates' => $deductionRates,
                'bonus_multiplier' => $bonusMultiplier,
            ],
            'scenarios' => [
                $this->buildScenario(
                    self::SCENARIO_CASH,
                    'cash',
                    'Сделка с наличкой',
                    $customerRate,
                    $carrierCash,
                    $defaultVatForm,
                    'cash',
                    'Заказчик платит безналом; перевозчик — наличные. Укажите ставку заказчика и «Ставка перевозчика, нал.».',
                    $deductionRates,
                    $managerId,
                    $orderDate,
                    $additionalExpenses,
                    $insurance,
                    $bonus,
                    $fixedExpense,
                ),
                $this->buildScenario(
                    self::SCENARIO_VAT,
                    'vat',
                    'Сделка с НДС',
                    $customerRate,
                    $carrierCashless,
                    $defaultVatForm,
                    $defaultVatForm,
                    'Заказчик и перевозчик — безнал (НДС). Укажите ставку заказчика и «Ставка перевозчика, безнал».',
                    $deductionRates,
                    $managerId,
                    $orderDate,
                    $additionalExpenses,
                    $insurance,
                    $bonus,
                    $fixedExpense,
                ),
            ],
            'summary' => [
                'hints' => $this->summaryHints($customerRate, $carrierCash, $carrierCashless, $deductionRates),
            ],
        ];

        if ($managerId <= 0 || $orderDate === null) {
            $result['warning'] = 'KPI в периоде не рассчитан: нужны менеджер и дата заказа.';
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveCustomerRate(array $input): ?float
    {
        $rate = $this->nullableAmount($input['customer_rate'] ?? null);

        if ($rate !== null) {
            return $rate;
        }

        return $this->nullableAmount($input['customer_with_vat'] ?? null)
            ?? $this->nullableAmount($input['customer_without_vat'] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildScenario(
        string $scenarioKey,
        string $paymentCategory,
        string $label,
        ?float $customerAmount,
        ?float $carrierAmount,
        string $customerPaymentForm,
        string $carrierPaymentForm,
        string $amountComment,
        array $deductionRates,
        int $managerId,
        mixed $orderDate,
        float $additionalExpenses,
        float $insurance,
        float $bonus,
        float $fixedExpense,
    ): array {
        $kpiRatesLabel = $this->deductionRatesLabel($paymentCategory, $deductionRates);

        $column = [
            'scenario_key' => $scenarioKey,
            'deal_type' => $paymentCategory,
            'deal_type_label' => $label,
            'amount_comment' => $amountComment,
            'customer_amount' => $customerAmount,
            'carrier_amount' => $carrierAmount,
            'customer_payment_form' => $customerPaymentForm,
            'carrier_payment_form' => $carrierPaymentForm,
            'kpi_deduction_rates_label' => $kpiRatesLabel,
            'kpi_deduction_amount' => null,
            'margin' => null,
            'margin_percent' => null,
            'comment' => null,
            'kpi_percent' => null,
        ];

        if ($customerAmount === null || $customerAmount <= 0) {
            $column['comment'] = 'Укажите ставку заказчика.';

            return $column;
        }

        $column['kpi_deduction_amount'] = round(
            $this->kpiConfigurationService->kpiDeductionAmount($customerAmount, $paymentCategory),
            2,
        );

        if ($carrierAmount === null || $carrierAmount < 0) {
            $column['comment'] = $scenarioKey === self::SCENARIO_CASH
                ? 'Укажите ставку перевозчика (нал.).'
                : 'Укажите ставку перевозчика (безнал).';

            return $column;
        }

        if ($managerId <= 0 || $orderDate === null) {
            $column['comment'] = 'Маржа по формуле: укажите менеджера и дату для KPI периода.';

            return $column;
        }

        $compensationPayload = [
            'customer_rate' => $customerAmount,
            'carrier_rate' => $carrierAmount,
            'additional_expenses' => $additionalExpenses,
            'insurance' => $insurance,
            'bonus' => $bonus,
            'manager_id' => $managerId,
            'order_date' => $orderDate,
            'customer_payment_form' => $customerPaymentForm,
            'carrier_payment_form' => $carrierPaymentForm,
            'contractors_costs' => [
                ['payment_form' => $carrierPaymentForm, 'amount' => $carrierAmount],
            ],
        ];

        $kpiDeduction = (float) $column['kpi_deduction_amount'];
        $evaluation = $this->orderCompensationService->calculateMarginScenario($compensationPayload, $paymentCategory);
        $delta = (float) $evaluation['delta'];
        $marginPercent = $customerAmount > 0 ? ($delta / $customerAmount) * 100 : 0.0;

        $column['margin'] = round($delta, 2);
        $column['margin_percent'] = round($marginPercent, 2);
        $column['kpi_percent'] = round((float) $evaluation['kpi_percent'], 2);
        $column['comment'] = sprintf(
            'Заказчик %s ₽, вычет KPI %s ₽ (%s), перевозчик %s ₽, доп. расходы %s ₽ (в т.ч. бонус с коэфф.). Маржа %s ₽ (%s%%).',
            $this->formatAmount($customerAmount),
            $this->formatAmount($kpiDeduction),
            $kpiRatesLabel,
            $this->formatAmount($carrierAmount),
            $this->formatAmount($fixedExpense),
            $this->formatAmount($delta),
            $this->formatPercent($marginPercent),
        );

        return $column;
    }

    /**
     * @return list<string>
     */
    /**
     * @param  array{
     *     vat_percent: float,
     *     vat_zero_22_percent: float,
     *     cash_primary_percent: float,
     *     cash_secondary_percent: float,
     * }  $deductionRates
     * @return list<string>
     */
    private function summaryHints(
        ?float $customerRate,
        ?float $carrierCash,
        ?float $carrierCashless,
        array $deductionRates,
    ): array {
        $cashLabel = $this->deductionRatesLabel('cash', $deductionRates);
        $vatLabel = $this->deductionRatesLabel('vat', $deductionRates);

        return [
            'Заказчик — одна ставка (оплата только безналом). Перевозчик — отдельно наличные и безнал.',
            sprintf(
                'Вычеты KPI из настроек мотивации: «Сделка с наличкой» — %s с суммы заказчика; «Сделка с НДС» — %s.',
                $cashLabel,
                $vatLabel,
            ),
            'Сначала заполните ставку заказчика и перевозчика (нал.) — появится обзор «Сделка с наличкой». Затем ставку перевозчика (безнал) — обзор «Сделка с НДС».',
            $this->filledFieldsHint($customerRate, $carrierCash, $carrierCashless),
        ];
    }

    /**
     * @param  array{
     *     vat_percent: float,
     *     vat_zero_22_percent: float,
     *     cash_primary_percent: float,
     *     cash_secondary_percent: float,
     * }  $deductionRates
     */
    private function deductionRatesLabel(string $paymentCategory, array $deductionRates): string
    {
        if ($paymentCategory === 'cash') {
            return sprintf(
                '%s%% + %s%%',
                $this->formatPercent((float) $deductionRates['cash_primary_percent']),
                $this->formatPercent((float) $deductionRates['cash_secondary_percent']),
            );
        }

        if ($paymentCategory === 'vat_zero_22') {
            return sprintf(
                '%s%%',
                $this->formatPercent((float) $deductionRates['vat_zero_22_percent']),
            );
        }

        return sprintf(
            '%s%%',
            $this->formatPercent((float) $deductionRates['vat_percent']),
        );
    }

    private function filledFieldsHint(?float $customerRate, ?float $carrierCash, ?float $carrierCashless): string
    {
        $parts = [];
        if ($customerRate !== null) {
            $parts[] = 'ставка заказчика';
        }
        if ($carrierCash !== null) {
            $parts[] = 'перевозчик, нал.';
        }
        if ($carrierCashless !== null) {
            $parts[] = 'перевозчик, безнал';
        }

        if ($parts === []) {
            return 'Заполните суммы в таблице.';
        }

        return 'Учтено: '.implode(', ', $parts).'.';
    }

    private function nullableAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $float = (float) $value;

        return $float >= 0 ? $float : null;
    }

    private function formatAmount(float $value): string
    {
        return number_format($value, 2, '.', ' ');
    }

    private function formatPercent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
