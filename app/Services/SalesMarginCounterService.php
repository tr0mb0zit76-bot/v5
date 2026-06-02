<?php

declare(strict_types=1);

namespace App\Services;

class SalesMarginCounterService
{
    public const SCENARIO_VAT = 'vat';

    public const SCENARIO_CASH = 'cash';

    public const SCENARIO_VAT_ZERO_22 = 'vat_zero_22';

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

        $customerWithout = $this->nullableAmount($input['customer_without_vat'] ?? null);
        $customerWith = $this->nullableAmount($input['customer_with_vat'] ?? null);
        $carrierWithout = $this->nullableAmount($input['carrier_without_vat'] ?? null);
        $carrierWith = $this->nullableAmount($input['carrier_with_vat'] ?? null);

        $result = [
            'fixed_expense' => round($fixedExpense, 2),
            'scenarios' => [
                $this->buildScenario(
                    self::SCENARIO_VAT,
                    'vat',
                    'НДС',
                    $customerWith ?? $customerWithout,
                    $carrierWith ?? $carrierWithout,
                    'vat_0',
                    'vat_0',
                    'НДС: сочетания ставок НДС (в т.ч. 0% / 0%), безнал; наличные у заказчика без наличных у перевозчика.',
                    $managerId,
                    $orderDate,
                    $additionalExpenses,
                    $insurance,
                    $bonus,
                    $fixedExpense,
                ),
                $this->buildScenario(
                    self::SCENARIO_CASH,
                    'cash',
                    'Наличка',
                    $customerWithout ?? $customerWith,
                    $carrierWithout ?? $carrierWith,
                    'cash',
                    'cash',
                    'Наличка: наличные у заказчика и у перевозчика; два вычета KPI с суммы заказчика.',
                    $managerId,
                    $orderDate,
                    $additionalExpenses,
                    $insurance,
                    $bonus,
                    $fixedExpense,
                ),
                $this->buildScenario(
                    self::SCENARIO_VAT_ZERO_22,
                    'vat_zero_22',
                    'НДС 0% / 22%',
                    $customerWith ?? $customerWithout,
                    $carrierWith ?? $carrierWithout,
                    'vat_0',
                    'vat_22',
                    'Заказчик с НДС 0%, перевозчик с НДС 22% (рейс или плечо); к марже добавляется доплата из бюджета.',
                    $managerId,
                    $orderDate,
                    $additionalExpenses,
                    $insurance,
                    $bonus,
                    $fixedExpense,
                ),
            ],
            'summary' => [
                'hints' => $this->summaryHints($customerWithout, $customerWith, $carrierWithout, $carrierWith),
            ],
        ];

        if ($managerId <= 0 || $orderDate === null) {
            $result['warning'] = 'KPI в периоде не рассчитан: нужны менеджер и дата заказа.';
        }

        return $result;
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
        int $managerId,
        mixed $orderDate,
        float $additionalExpenses,
        float $insurance,
        float $bonus,
        float $fixedExpense,
    ): array {
        $column = [
            'scenario_key' => $scenarioKey,
            'deal_type' => $paymentCategory,
            'deal_type_label' => $label,
            'amount_comment' => $amountComment,
            'customer_amount' => $customerAmount,
            'carrier_amount' => $carrierAmount,
            'customer_payment_form' => $customerPaymentForm,
            'carrier_payment_form' => $carrierPaymentForm,
            'margin' => null,
            'margin_percent' => null,
            'comment' => null,
            'kpi_percent' => null,
        ];

        if ($customerAmount === null || $customerAmount <= 0) {
            $column['comment'] = 'Укажите сумму заказчика для этого варианта.';

            return $column;
        }

        if ($carrierAmount === null || $carrierAmount < 0) {
            $column['comment'] = 'Укажите сумму перевозчика для этого варианта.';

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

        $evaluation = $this->orderCompensationService->calculateMarginScenario($compensationPayload, $paymentCategory);
        $delta = (float) $evaluation['delta'];
        $marginPercent = $customerAmount > 0 ? ($delta / $customerAmount) * 100 : 0.0;

        $column['margin'] = round($delta, 2);
        $column['margin_percent'] = round($marginPercent, 2);
        $column['kpi_percent'] = round((float) $evaluation['kpi_percent'], 2);
        $column['comment'] = sprintf(
            'Заказчик %s ₽, перевозчик %s ₽, доп. расходы %s ₽ (в т.ч. бонус с коэфф.). Маржа %s ₽ (%s%%). KPI %s%%.',
            $this->formatAmount($customerAmount),
            $this->formatAmount($carrierAmount),
            $this->formatAmount($fixedExpense),
            $this->formatAmount($delta),
            $this->formatPercent($marginPercent),
            $this->formatPercent((float) $evaluation['kpi_percent']),
        );

        return $column;
    }

    /**
     * @return list<string>
     */
    private function summaryHints(
        ?float $customerWithout,
        ?float $customerWith,
        ?float $carrierWithout,
        ?float $carrierWith,
    ): array {
        return [
            'Поля «Без НДС» и «С НДС» не связаны: вводите суммы независимо.',
            'Три столбца: НДС, наличка (только при наличных у перевозчика), НДС 0% у заказчика и 22% у перевозчика.',
            $this->filledFieldsHint($customerWithout, $customerWith, $carrierWithout, $carrierWith),
        ];
    }

    private function filledFieldsHint(
        ?float $customerWithout,
        ?float $customerWith,
        ?float $carrierWithout,
        ?float $carrierWith,
    ): string {
        $parts = [];
        if ($customerWithout !== null) {
            $parts[] = 'заказчик без НДС';
        }
        if ($customerWith !== null) {
            $parts[] = 'заказчик с НДС';
        }
        if ($carrierWithout !== null) {
            $parts[] = 'перевозчик без НДС';
        }
        if ($carrierWith !== null) {
            $parts[] = 'перевозчик с НДС';
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
