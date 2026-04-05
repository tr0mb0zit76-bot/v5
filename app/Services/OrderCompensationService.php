<?php

namespace App\Services;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\SalaryCoefficient;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderCompensationService
{
    public function __construct(
        private readonly DealTypeClassifier $dealTypeClassifier,
        private readonly PeriodCalculator $periodCalculator,
        private readonly KpiConfigurationService $kpiConfigurationService,
    ) {}

    public function recalculateImpactedPeriods(
        Order $order,
        ?int $previousManagerId = null,
        ?string $previousOrderDate = null,
    ): void {
        $targets = collect([
            [
                'manager_id' => $previousManagerId,
                'order_date' => $previousOrderDate,
            ],
            [
                'manager_id' => $order->manager_id,
                'order_date' => optional($order->order_date)?->toDateString(),
            ],
        ])->filter(function (array $target): bool {
            return filled($target['manager_id']) && filled($target['order_date']);
        })->unique(fn (array $target): string => $target['manager_id'].'|'.$target['order_date']);

        foreach ($targets as $target) {
            $this->recalculateManagerPeriod((int) $target['manager_id'], (string) $target['order_date']);
        }
    }

    public function recalculateManagerPeriod(int $managerId, string $date): void
    {
        $period = $this->periodCalculator->getPeriodForDate($date);

        $orders = Order::query()
            ->where('manager_id', $managerId)
            ->whereBetween('order_date', [$period['start'], $period['end']])
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            )
            ->orderBy('id')
            ->get();

        foreach ($orders as $order) {
            $calculation = $this->calculateOrder($order);

            $order->forceFill([
                'kpi_percent' => $calculation['kpi_percent'],
                'delta' => $calculation['delta'],
                'salary_accrued' => $calculation['salary_accrued'],
            ])->saveQuietly();

            $this->syncFinancialTerms($order);
            $this->syncPaymentSchedules($order);
        }
    }

    /**
     * @return array{kpi_percent: float, delta: float, salary_accrued: float, deal_type: string, period_info: array<string, int|float>}
     */
    public function calculateOrder(Order $order): array
    {
        $dealType = $this->dealTypeClassifier->classify($order);

        if ($dealType === 'unknown' || $order->order_date === null) {
            return [
                'kpi_percent' => 0.0,
                'delta' => 0.0,
                'salary_accrued' => 0.0,
                'deal_type' => 'unknown',
                'period_info' => [],
            ];
        }

        $period = $this->periodCalculator->getPeriodForDate($order->order_date->toDateString());
        $periodStats = $this->periodCalculator->getManagerPeriodStats(
            (int) $order->manager_id,
            $period['start'],
            $period['end'],
        );

        $kpiPercent = $this->resolveKpiPercent($dealType, $periodStats['direct_ratio']);
        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();
        $customerRate = (float) ($order->customer_rate ?? 0);
        $carrierRate = (float) ($order->carrier_rate ?? 0);
        $additionalExpenses = (float) ($order->additional_expenses ?? 0);
        $insurance = (float) ($order->insurance ?? 0);
        $bonus = (float) ($order->bonus ?? 0);
        $delta = $customerRate - ($customerRate * ($kpiPercent / 100)) - ($carrierRate + $additionalExpenses + $insurance + ($bonus * $bonusMultiplier));

        $salaryCoefficient = SalaryCoefficient::getForManagerOnDate(
            (int) $order->manager_id,
            $order->order_date->toDateString(),
        );

        $salaryAccrued = $this->resolveSalaryAccrued($delta, $salaryCoefficient);

        return [
            'kpi_percent' => round($kpiPercent, 2),
            'delta' => round($delta, 2),
            'salary_accrued' => round($salaryAccrued, 2),
            'deal_type' => $dealType,
            'period_info' => $periodStats,
        ];
    }

    private function resolveKpiPercent(string $dealType, float $directRatio): float
    {
        $thresholds = collect($this->kpiConfigurationService->groupedThresholds());

        if ($thresholds->isEmpty()) {
            return 0.0;
        }

        $matchedThreshold = $thresholds->first(function (array $threshold) use ($directRatio): bool {
            return $directRatio >= (float) $threshold['threshold_from']
                && $directRatio <= (float) $threshold['threshold_to'];
        });

        if ($matchedThreshold === null) {
            $matchedThreshold = $thresholds
                ->sortByDesc('threshold_from')
                ->first(fn (array $threshold): bool => $directRatio >= (float) $threshold['threshold_from'])
                ?? $thresholds->last();
        }

        return (float) ($dealType === 'direct'
            ? ($matchedThreshold['direct_kpi'] ?? 0)
            : ($matchedThreshold['indirect_kpi'] ?? 0));
    }

    private function resolveSalaryAccrued(float $delta, ?SalaryCoefficient $salaryCoefficient): float
    {
        if ($salaryCoefficient === null) {
            return $delta / 2;
        }

        $baseSalary = (float) ($salaryCoefficient->base_salary ?? 0);
        $bonusPercent = (float) ($salaryCoefficient->bonus_percent ?? 0);

        if ($baseSalary === 0.0 && $bonusPercent === 0.0) {
            return $delta / 2;
        }

        return ($delta * ($bonusPercent / 100)) + $baseSalary;
    }

    private function syncFinancialTerms(Order $order): void
    {
        if (! Schema::hasTable('financial_terms')) {
            return;
        }

        $financialTerm = FinancialTerm::query()->firstOrNew([
            'order_id' => $order->id,
        ]);

        $paymentTerms = $this->decodePaymentTerms($order);
        $contractorsCosts = $this->extractContractorsCosts($order);
        $additionalCosts = is_array($financialTerm->additional_costs) ? $financialTerm->additional_costs : [];
        $totalCost = collect($contractorsCosts)->sum(fn (array $cost): float => (float) ($cost['amount'] ?? 0))
            + collect($additionalCosts)->sum(fn (array $cost): float => (float) ($cost['amount'] ?? 0));

        $financialTerm->client_price = $order->customer_rate;
        $financialTerm->client_currency = $financialTerm->client_currency ?: 'RUB';
        $financialTerm->contractors_costs = $contractorsCosts;
        $financialTerm->total_cost = round($totalCost, 2);
        $financialTerm->margin = $order->delta;

        if (Schema::hasColumn('financial_terms', 'client_payment_terms')) {
            $financialTerm->client_payment_terms = $this->formatPaymentScheduleSummary(
                (array) data_get($paymentTerms, 'client.payment_schedule', [])
            );
        }

        if (! $financialTerm->exists) {
            $financialTerm->additional_costs = [];
        }

        $financialTerm->save();
    }

    private function syncPaymentSchedules(Order $order): void
    {
        if (! Schema::hasTable('payment_schedules')) {
            return;
        }

        DB::table('payment_schedules')->where('order_id', $order->id)->delete();

        $paymentTerms = $this->decodePaymentTerms($order);
        $rows = [];

        $customerRows = $this->buildPaymentScheduleRows(
            $order,
            'customer',
            (float) ($order->customer_rate ?? 0),
            (array) data_get($paymentTerms, 'client.payment_schedule', []),
        );

        foreach ($this->extractContractorsCosts($order) as $cost) {
            $rows = [
                ...$rows,
                ...$this->buildPaymentScheduleRows(
                    $order,
                    'carrier',
                    (float) ($cost['amount'] ?? 0),
                    (array) ($cost['payment_schedule'] ?? []),
                ),
            ];
        }

        $rows = [...$customerRows, ...$rows];

        if ($rows === []) {
            return;
        }

        DB::table('payment_schedules')->insert($rows);
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @return list<array<string, mixed>>
     */
    private function buildPaymentScheduleRows(Order $order, string $party, float $amount, array $schedule): array
    {
        if ($amount <= 0) {
            return [];
        }

        $rows = [];
        $hasPrepayment = (bool) ($schedule['has_prepayment'] ?? false);
        $prepaymentRatio = max(0, min(100, (float) ($schedule['prepayment_ratio'] ?? 0)));
        $prepaymentAmount = $hasPrepayment ? round($amount * ($prepaymentRatio / 100), 2) : 0.0;
        $finalAmount = round($amount - $prepaymentAmount, 2);

        if ($hasPrepayment && $prepaymentAmount > 0) {
            $rows[] = [
                'order_id' => $order->id,
                'party' => $party,
                'type' => 'prepayment',
                'amount' => $prepaymentAmount,
                'planned_date' => $this->resolveScheduleDate(
                    $order,
                    $party,
                    (string) ($schedule['prepayment_mode'] ?? 'fttn'),
                    (int) ($schedule['prepayment_days'] ?? 0),
                    true,
                ),
                'actual_date' => null,
                'status' => 'pending',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($finalAmount > 0) {
            $rows[] = [
                'order_id' => $order->id,
                'party' => $party,
                'type' => 'final',
                'amount' => $finalAmount,
                'planned_date' => $this->resolveScheduleDate(
                    $order,
                    $party,
                    (string) ($schedule['postpayment_mode'] ?? 'ottn'),
                    (int) ($schedule['postpayment_days'] ?? 0),
                    false,
                ),
                'actual_date' => null,
                'status' => 'pending',
                'notes' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        return $rows;
    }

    private function resolveScheduleDate(
        Order $order,
        string $party,
        string $mode,
        int $days,
        bool $isPrepayment,
    ): ?string {
        $baseDate = match (strtolower($mode)) {
            'ottn' => $party === 'customer'
                ? $order->track_received_date_customer
                : $order->track_received_date_carrier,
            default => $isPrepayment ? $order->loading_date : $order->unloading_date,
        };

        if ($baseDate === null) {
            return null;
        }

        return $this->addWorkingDays(Carbon::parse($baseDate), max(0, $days))->toDateString();
    }

    private function addWorkingDays(Carbon $date, int $days): Carbon
    {
        $result = $date->copy();
        $addedDays = 0;

        while ($addedDays < $days) {
            $result->addDay();

            if ($result->isWeekday()) {
                $addedDays++;
            }
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePaymentTerms(Order $order): array
    {
        $decoded = json_decode((string) $order->payment_terms, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractContractorsCosts(Order $order): array
    {
        if (! Schema::hasTable('financial_terms')) {
            return [];
        }

        $financialTerm = FinancialTerm::query()
            ->where('order_id', $order->id)
            ->first();

        return is_array($financialTerm?->contractors_costs) ? $financialTerm->contractors_costs : [];
    }

    /**
     * @param  array<string, mixed>  $schedule
     */
    private function formatPaymentScheduleSummary(array $schedule): ?string
    {
        $postpaymentDays = (int) Arr::get($schedule, 'postpayment_days', 0);
        $postpaymentMode = strtoupper((string) Arr::get($schedule, 'postpayment_mode', 'ottn'));
        $hasPrepayment = (bool) Arr::get($schedule, 'has_prepayment', false);

        if (! $hasPrepayment) {
            return $postpaymentDays > 0 ? "{$postpaymentDays} дн {$postpaymentMode}" : null;
        }

        $prepaymentRatio = (int) Arr::get($schedule, 'prepayment_ratio', 0);
        $prepaymentDays = (int) Arr::get($schedule, 'prepayment_days', 0);
        $prepaymentMode = strtoupper((string) Arr::get($schedule, 'prepayment_mode', 'fttn'));
        $postpaymentRatio = max(0, 100 - $prepaymentRatio);

        return "{$prepaymentRatio}/{$postpaymentRatio}, {$prepaymentDays} дн {$prepaymentMode} / {$postpaymentDays} дн {$postpaymentMode}";
    }
}
