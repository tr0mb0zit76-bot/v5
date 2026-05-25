<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CashToCashMarginCalculator;
use App\Support\PaymentAmountVatConverter;
use App\Support\PaymentFormDictionary;

class SalesMarginCounterService
{
    public const DEFAULT_MIN_MARGIN_PERCENT = 10.0;

    public const ANCHOR_CUSTOMER_WITHOUT_VAT = 'customer_without_vat';

    public const ANCHOR_CUSTOMER_WITH_VAT = 'customer_with_vat';

    public const ANCHOR_CARRIER_WITHOUT_VAT = 'carrier_without_vat';

    public const ANCHOR_CARRIER_WITH_VAT = 'carrier_with_vat';

    public const ANCHOR_MARGIN = 'margin';

    /**
     * @var list<string>
     */
    public const ANCHOR_FIELDS = [
        self::ANCHOR_CUSTOMER_WITHOUT_VAT,
        self::ANCHOR_CUSTOMER_WITH_VAT,
        self::ANCHOR_CARRIER_WITHOUT_VAT,
        self::ANCHOR_CARRIER_WITH_VAT,
        self::ANCHOR_MARGIN,
    ];

    public function __construct(
        private readonly OrderCompensationService $orderCompensationService,
        private readonly KpiConfigurationService $kpiConfigurationService,
    ) {}

    /**
     * @param  array{
     *     anchor_field?: string|null,
     *     customer_without_vat?: float|null,
     *     customer_with_vat?: float|null,
     *     carrier_without_vat?: float|null,
     *     carrier_with_vat?: float|null,
     *     margin?: float|null,
     *     additional_expenses?: float|null,
     *     insurance?: float|null,
     *     bonus?: float|null,
     *     customer_payment_form?: string|null,
     *     carrier_payment_form?: string|null,
     *     min_margin_percent?: float|null,
     *     manager_id?: int|null,
     *     order_date?: string|null,
     * }  $input
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

        $inputCustomerWithout = $this->nullableAmount($input['customer_without_vat'] ?? null);
        $inputCustomerWith = $this->nullableAmount($input['customer_with_vat'] ?? null);
        $inputCarrierWithout = $this->nullableAmount($input['carrier_without_vat'] ?? null);
        $inputCarrierWith = $this->nullableAmount($input['carrier_with_vat'] ?? null);
        $inputMargin = $this->nullableAmount($input['margin'] ?? null);

        $customerNet = $this->resolveSideNet(
            $anchor,
            self::ANCHOR_CUSTOMER_WITHOUT_VAT,
            self::ANCHOR_CUSTOMER_WITH_VAT,
            $inputCustomerWithout,
            $inputCustomerWith,
            $customerForm,
        );
        $carrierNet = $this->resolveSideNet(
            $anchor,
            self::ANCHOR_CARRIER_WITHOUT_VAT,
            self::ANCHOR_CARRIER_WITH_VAT,
            $inputCarrierWithout,
            $inputCarrierWith,
            $carrierForm,
        );
        $margin = $inputMargin;

        $contractorsCosts = [['payment_form' => $carrierForm, 'amount' => $carrierNet ?? 0]];
        $cashToCash = CashToCashMarginCalculator::isCashToCash($customerForm, $contractorsCosts);

        $kpiContext = $this->orderCompensationService->calculateRealtime([
            'customer_rate' => 1,
            'carrier_rate' => 0,
            'manager_id' => $managerId,
            'order_date' => $orderDate,
            'customer_payment_form' => $customerForm,
            'carrier_payment_form' => $carrierForm,
            'contractors_costs' => $contractorsCosts,
        ]);
        $kpiPercent = (float) ($kpiContext['kpi_percent'] ?? 0);
        $dealType = (string) ($kpiContext['deal_type'] ?? 'unknown');

        $result = [
            'anchor_field' => $anchor !== '' ? $anchor : null,
            'deal_type' => $dealType,
            'deal_type_label' => $this->dealTypeLabel($dealType),
            'kpi_percent' => $kpiPercent,
            'cash_to_cash' => $cashToCash,
            'min_margin_percent' => round($minMarginPercent, 2),
            'customer_payment_form' => $customerForm,
            'carrier_payment_form' => $carrierForm,
            'fixed_expense' => round($fixedExpense, 2),
            'fields' => $this->emptyFields($customerForm, $carrierForm),
            'margin_percent' => null,
            'margin_quality' => null,
            'margin_quality_label' => null,
            'salary_accrued' => null,
            'summary' => [
                'scenario' => 'empty',
                'hints' => [],
            ],
        ];

        if ($dealType === 'unknown' || $managerId <= 0 || $orderDate === null) {
            $result['error'] = 'Укажите менеджера и дату (для KPI нужен текущий период).';

            return $result;
        }

        $this->applyAnchorLogic(
            $anchor,
            $customerNet,
            $carrierNet,
            $margin,
            $kpiPercent,
            $minMarginPercent,
            $cashToCash,
            $fixedExpense,
        );

        $evaluation = null;
        if ($customerNet !== null && $customerNet > 0 && $carrierNet !== null && $carrierNet >= 0) {
            $evaluation = $this->orderCompensationService->calculateRealtime([
                'customer_rate' => $customerNet,
                'carrier_rate' => max(0.0, $carrierNet),
                'additional_expenses' => $additionalExpenses,
                'insurance' => $insurance,
                'bonus' => $bonus,
                'manager_id' => $managerId,
                'order_date' => $orderDate,
                'customer_payment_form' => $customerForm,
                'carrier_payment_form' => $carrierForm,
                'contractors_costs' => [['payment_form' => $carrierForm, 'amount' => max(0.0, $carrierNet)]],
            ]);
            $margin = (float) ($evaluation['delta'] ?? 0);
        } elseif ($customerNet !== null && $carrierNet !== null && $margin === null) {
            $margin = $this->evaluateMargin($customerNet, $carrierNet, $kpiPercent, $cashToCash, $fixedExpense);
        }

        $result['fields'] = $this->buildFields($customerNet, $carrierNet, $margin, $customerForm, $carrierForm);
        $result['summary'] = $this->buildSummary(
            $customerNet,
            $carrierNet,
            $margin,
            $kpiPercent,
            $minMarginPercent,
            $cashToCash,
            $fixedExpense,
            $customerForm,
            $carrierForm,
        );

        if ($evaluation !== null && $customerNet !== null && $customerNet > 0) {
            $this->applyEvaluation($result, $evaluation, $customerNet, $minMarginPercent);
        }

        return $result;
    }

    /**
     * @param-out float|null $customerNet
     * @param-out float|null $carrierNet
     * @param-out float|null $margin
     */
    private function applyAnchorLogic(
        string $anchor,
        ?float &$customerNet,
        ?float &$carrierNet,
        ?float &$margin,
        float $kpiPercent,
        float $minMarginPercent,
        bool $cashToCash,
        float $fixedExpense,
    ): void {
        if ($anchor === self::ANCHOR_CUSTOMER_WITHOUT_VAT || $anchor === self::ANCHOR_CUSTOMER_WITH_VAT) {
            if ($customerNet !== null && $carrierNet !== null) {
                $margin = $this->evaluateMargin($customerNet, $carrierNet, $kpiPercent, $cashToCash, $fixedExpense);
            } elseif ($customerNet !== null && $margin !== null) {
                $carrierNet = $this->deriveCarrierNet($customerNet, $margin, $kpiPercent, $cashToCash, $fixedExpense);
            }

            return;
        }

        if ($anchor === self::ANCHOR_CARRIER_WITHOUT_VAT || $anchor === self::ANCHOR_CARRIER_WITH_VAT) {
            if ($customerNet !== null && $carrierNet !== null) {
                $margin = $this->evaluateMargin($customerNet, $carrierNet, $kpiPercent, $cashToCash, $fixedExpense);
            } elseif ($carrierNet !== null && $margin !== null) {
                $customerNet = $this->deriveCustomerNet($carrierNet, $margin, $kpiPercent, $cashToCash, $fixedExpense);
            }

            return;
        }

        if ($anchor === self::ANCHOR_MARGIN) {
            if ($customerNet !== null && $margin !== null) {
                $carrierNet = $this->deriveCarrierNet($customerNet, $margin, $kpiPercent, $cashToCash, $fixedExpense);
            } elseif ($carrierNet !== null && $margin !== null) {
                $customerNet = $this->deriveCustomerNet($carrierNet, $margin, $kpiPercent, $cashToCash, $fixedExpense);
            }

            return;
        }

        if ($customerNet !== null && $carrierNet !== null) {
            $margin = $this->evaluateMargin($customerNet, $carrierNet, $kpiPercent, $cashToCash, $fixedExpense);

            return;
        }

        if ($customerNet !== null && $margin !== null) {
            $carrierNet = $this->deriveCarrierNet($customerNet, $margin, $kpiPercent, $cashToCash, $fixedExpense);

            return;
        }

        if ($carrierNet !== null && $margin !== null) {
            $customerNet = $this->deriveCustomerNet($carrierNet, $margin, $kpiPercent, $cashToCash, $fixedExpense);
        }
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
     * @return array{scenario: string, hints: list<string>}
     */
    private function buildSummary(
        ?float $customerNet,
        ?float $carrierNet,
        ?float $margin,
        float $kpiPercent,
        float $minMarginPercent,
        bool $cashToCash,
        float $fixedExpense,
        string $customerForm,
        string $carrierForm,
    ): array {
        $hints = [];

        if ($customerNet !== null && $customerNet > 0 && $carrierNet !== null && $margin !== null) {
            return [
                'scenario' => 'both_rates',
                'hints' => $cashToCash
                    ? [
                        'Наличные у заказчика и перевозчика: маржа = доход − расход, KPI не вычитается.',
                    ]
                    : [
                        'Введены обе ставки — маржа пересчитана по правилам заказа.',
                        sprintf('KPI периода: %s%%.', $this->formatPercent($kpiPercent)),
                    ],
            ];
        }

        if ($customerNet !== null && $customerNet > 0 && $margin !== null && $carrierNet === null) {
            return [
                'scenario' => 'customer_and_margin',
                'hints' => $cashToCash
                    ? [
                        'По ставке заказчика и целевой марже рассчитан перевозчик (доход − расход, без KPI).',
                    ]
                    : [
                        'По ставке заказчика и целевой марже рассчитан перевозчик.',
                        sprintf('KPI периода: %s%%.', $this->formatPercent($kpiPercent)),
                    ],
            ];
        }

        if ($carrierNet !== null && $margin !== null && ($customerNet === null || $customerNet <= 0)) {
            return [
                'scenario' => 'carrier_and_margin',
                'hints' => $cashToCash
                    ? [
                        'По ставке перевозчика и целевой марже рассчитана ставка заказчика (доход − расход, без KPI).',
                    ]
                    : [
                        'По ставке перевозчика и целевой марже рассчитана ставка заказчика.',
                        sprintf('KPI периода: %s%%.', $this->formatPercent($kpiPercent)),
                    ],
            ];
        }

        if ($customerNet !== null && $customerNet > 0 && $carrierNet === null) {
            $targetMargin = $customerNet * ($minMarginPercent / 100);
            $maxCarrier = $this->deriveCarrierNet($customerNet, $targetMargin, $kpiPercent, $cashToCash, $fixedExpense);
            $maxCarrierPair = PaymentAmountVatConverter::pairFromNet(max(0.0, $maxCarrier), $carrierForm);

            $hints[] = sprintf(
                'При марже %s%% максимум перевозчику: %s (без НДС).',
                $this->formatPercent($minMarginPercent),
                $this->formatMoney(max(0.0, $maxCarrier)),
            );

            if ($maxCarrierPair['with_vat'] !== null) {
                $hints[] = sprintf('С НДС (%s%%): %s.', $this->formatPercent($maxCarrierPair['vat_rate_percent']), $this->formatMoney($maxCarrierPair['with_vat']));
            }

            if ($cashToCash) {
                $hints[] = 'Наличные: доход − расход, KPI не вычитается.';
            } else {
                $hints[] = sprintf('KPI периода: %s%%.', $this->formatPercent($kpiPercent));
            }

            return [
                'scenario' => 'customer_only',
                'hints' => $hints,
            ];
        }

        if ($carrierNet !== null && $carrierNet > 0 && ($customerNet === null || $customerNet <= 0)) {
            $minCustomer = $this->suggestCustomerRate(
                $carrierNet + $fixedExpense,
                $kpiPercent,
                $minMarginPercent,
                $cashToCash,
            );
            $minCustomerPair = PaymentAmountVatConverter::pairFromNet($minCustomer, $customerForm);

            $hints[] = sprintf(
                'При марже %s%% минимум заказчику: %s (без НДС).',
                $this->formatPercent($minMarginPercent),
                $this->formatMoney($minCustomer),
            );

            if ($minCustomerPair['with_vat'] !== null) {
                $hints[] = sprintf('С НДС (%s%%): %s.', $this->formatPercent($minCustomerPair['vat_rate_percent']), $this->formatMoney($minCustomerPair['with_vat']));
            }

            if ($cashToCash) {
                $hints[] = 'Наличные: доход − расход, KPI не вычитается.';
            } else {
                $hints[] = sprintf('KPI периода: %s%%.', $this->formatPercent($kpiPercent));
            }

            return [
                'scenario' => 'carrier_only',
                'hints' => $hints,
            ];
        }

        if ($margin !== null) {
            $hints[] = 'Укажите ставку заказчика или перевозчика — вторая сторона будет рассчитана из маржи.';
        } else {
            $hints[] = 'Заполните любое из пяти полей — остальные пересчитаются автоматически.';
        }

        if ($cashToCash) {
            $hints[] = 'Наличные: доход − расход, KPI не вычитается.';
        } else {
            $hints[] = sprintf('KPI периода: %s%%.', $this->formatPercent($kpiPercent));
        }

        return [
            'scenario' => 'empty',
            'hints' => $hints,
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

    private function resolveSideNet(
        string $anchor,
        string $withoutAnchor,
        string $withAnchor,
        ?float $without,
        ?float $with,
        string $paymentForm,
    ): ?float {
        if ($anchor === $withoutAnchor && $without !== null) {
            return max(0.0, $without);
        }

        if ($anchor === $withAnchor && $with !== null) {
            return max(0.0, PaymentAmountVatConverter::netFromGrossAmount($with, $paymentForm));
        }

        if ($without !== null) {
            return max(0.0, $without);
        }

        if ($with !== null) {
            return max(0.0, PaymentAmountVatConverter::netFromGrossAmount($with, $paymentForm));
        }

        return null;
    }

    private function evaluateMargin(
        float $customerNet,
        float $carrierNet,
        float $kpiPercent,
        bool $cashToCash,
        float $fixedExpense,
    ): float {
        return round(
            CashToCashMarginCalculator::margin(
                $customerNet,
                $carrierNet + $fixedExpense,
                $kpiPercent,
                $cashToCash,
            ),
            2,
        );
    }

    private function deriveCarrierNet(
        float $customerNet,
        float $margin,
        float $kpiPercent,
        bool $cashToCash,
        float $fixedExpense,
    ): float {
        if ($cashToCash) {
            return round(max(0.0, $customerNet - $fixedExpense - $margin), 2);
        }

        return round(max(0.0, $customerNet - ($customerNet * ($kpiPercent / 100)) - $fixedExpense - $margin), 2);
    }

    private function deriveCustomerNet(
        float $carrierNet,
        float $margin,
        float $kpiPercent,
        bool $cashToCash,
        float $fixedExpense,
    ): float {
        $totalExpense = $carrierNet + $fixedExpense + $margin;

        if ($cashToCash) {
            return round(max(0.0, $totalExpense), 2);
        }

        $denominator = 1 - ($kpiPercent / 100);

        if ($denominator <= 0) {
            return 0.0;
        }

        return round(max(0.0, $totalExpense / $denominator), 2);
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array{kpi_percent: float, delta: float, salary_accrued: float, deal_type: string}  $evaluation
     */
    private function applyEvaluation(array &$result, array $evaluation, float $customerRate, float $minMarginPercent): void
    {
        $delta = (float) ($evaluation['delta'] ?? 0);
        $marginPercent = $customerRate > 0 ? ($delta / $customerRate) * 100 : 0.0;
        $quality = $this->marginQuality($marginPercent, $minMarginPercent);

        $result['margin_percent'] = round($marginPercent, 2);
        $result['margin_quality'] = $quality;
        $result['margin_quality_label'] = $this->marginQualityLabel($quality);
        $result['salary_accrued'] = round((float) ($evaluation['salary_accrued'] ?? 0), 2);
        $result['fields']['margin'] = round($delta, 2);
    }

    private function suggestCustomerRate(
        float $totalExpense,
        float $kpiPercent,
        float $minMarginPercent,
        bool $cashToCash,
    ): float {
        if ($totalExpense <= 0) {
            return 0.0;
        }

        $denominator = $cashToCash
            ? (1 - ($minMarginPercent / 100))
            : (1 - ($kpiPercent / 100) - ($minMarginPercent / 100));

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

    private function formatMoney(float $value): string
    {
        return number_format($value, 0, '.', ' ').' ₽';
    }
}
