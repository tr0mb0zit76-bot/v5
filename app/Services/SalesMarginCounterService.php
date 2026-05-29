<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\PaymentAmountVatConverter;
use App\Support\PaymentFormDictionary;

class SalesMarginCounterService
{
    public const SCENARIO_DIRECT_WITH_VAT = 'direct_with_vat';

    public const SCENARIO_DIRECT_WITHOUT_VAT = 'direct_without_vat';

    public const SCENARIO_INDIRECT = 'indirect';

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

        $defaultVatForm = PaymentFormDictionary::defaultClientVatCode();

        $result = [
            'fixed_expense' => round($fixedExpense, 2),
            'scenarios' => [
                $this->buildScenario(
                    self::SCENARIO_DIRECT_WITH_VAT,
                    'direct',
                    'Прямая с НДС',
                    $customerWith,
                    $carrierWith,
                    $defaultVatForm,
                    $defaultVatForm,
                    'Ставки из полей «С НДС» у заказчика и перевозчика.',
                    $managerId,
                    $orderDate,
                    $additionalExpenses,
                    $insurance,
                    $bonus,
                    $fixedExpense,
                ),
                $this->buildScenario(
                    self::SCENARIO_DIRECT_WITHOUT_VAT,
                    'direct',
                    'Прямая без НДС',
                    $customerWithout,
                    $carrierWithout,
                    'no_vat',
                    'no_vat',
                    'Ставки из полей «Без НДС» у заказчика и перевозчика.',
                    $managerId,
                    $orderDate,
                    $additionalExpenses,
                    $insurance,
                    $bonus,
                    $fixedExpense,
                ),
                $this->buildScenario(
                    self::SCENARIO_INDIRECT,
                    'indirect',
                    'Кривая (с НДС клиент, без НДС перевозчик)',
                    $this->indirectCustomerAmount($customerWith, $customerWithout, $defaultVatForm),
                    $carrierWithout,
                    $defaultVatForm,
                    'no_vat',
                    'Заказчик — поле «С НДС» (или пересчёт из «Без НДС»), перевозчик — «Без НДС».',
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
        string $dealType,
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
            'deal_type' => $dealType,
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

        $evaluation = $this->orderCompensationService->calculateMarginScenario($compensationPayload, $dealType);
        $delta = (float) $evaluation['delta'];
        $marginPercent = $customerAmount > 0 ? ($delta / $customerAmount) * 100 : 0.0;

        $column['margin'] = round($delta, 2);
        $column['margin_percent'] = round($marginPercent, 2);
        $column['kpi_percent'] = round((float) $evaluation['kpi_percent'], 2);
        $column['comment'] = sprintf(
            'Заказчик %s ₽, перевозчик %s ₽, доп. расходы %s ₽ (в т.ч. бонус с коэфф.). Маржа %s ₽ (%s%%). KPI периода %s%%.',
            $this->formatAmount($customerAmount),
            $this->formatAmount($carrierAmount),
            $this->formatAmount($fixedExpense),
            $this->formatAmount($delta),
            $this->formatPercent($marginPercent),
            $this->formatPercent((float) $evaluation['kpi_percent']),
        );

        return $column;
    }

    private function indirectCustomerAmount(?float $with, ?float $without, string $vatForm): ?float
    {
        if ($with !== null && $with > 0) {
            return $with;
        }

        if ($without !== null && $without > 0) {
            $pair = PaymentAmountVatConverter::pairFromNet($without, $vatForm);

            return isset($pair['with_vat']) ? (float) $pair['with_vat'] : $without;
        }

        return null;
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
            'Три столбца — три способа прочитать одни и те же цифры (прямая с НДС, прямая без НДС, кривая).',
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
