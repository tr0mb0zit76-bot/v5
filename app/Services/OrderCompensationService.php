<?php

namespace App\Services;

use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\SalaryCoefficient;
use App\Support\CarrierRateFromFinancialTerms;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PaymentScheduleSummaryFormatter;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OrderCompensationService
{
    public function __construct(
        private readonly DealTypeClassifier $dealTypeClassifier,
        private readonly PeriodCalculator $periodCalculator,
        private readonly KpiConfigurationService $kpiConfigurationService,
        private readonly OrderDocumentRequirementService $orderDocumentRequirementService,
    ) {}

    public function recalculateImpactedPeriods(
        Order $order,
        ?int $previousManagerId = null,
        ?string $previousOrderDate = null,
        bool $dealTypeChanged = false,
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

        // If deal type changed, recalculate entire period for all managers
        if ($dealTypeChanged) {
            $affectedDates = collect([
                $previousOrderDate,
                optional($order->order_date)?->toDateString(),
            ])->filter()->unique()->all();

            foreach ($affectedDates as $date) {
                $this->recalculatePeriodForAllManagers($date);
            }
        }
    }

    public function recalculatePeriodForAllManagers(string $date): void
    {
        $period = $this->periodCalculator->getPeriodForDate($date);

        $managerIds = Order::query()
            ->whereBetween('order_date', [$period['start'], $period['end']])
            ->whereNotNull('manager_id')
            ->when(
                Schema::hasColumn('orders', 'deleted_at'),
                fn ($query) => $query->whereNull('deleted_at')
            )
            ->distinct()
            ->pluck('manager_id')
            ->filter()
            ->all();

        foreach ($managerIds as $managerId) {
            $this->recalculateManagerPeriod((int) $managerId, $date);
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
            ->when(
                Schema::hasTable('financial_terms'),
                fn ($query) => $query->with('financialTerms'),
            )
            ->when(
                Schema::hasTable('order_documents'),
                fn ($query) => $query->with('documents'),
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

        $kpiPercent = $this->kpiConfigurationService->resolveKpiPercentForDeal($dealType, $periodStats['direct_ratio']);
        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();
        $customerRate = (float) ($order->customer_rate ?? 0);
        $carrierRate = CarrierRateFromFinancialTerms::resolveForOrder($order);
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
        ];
    }

    /**
     * @return array{kpi_percent: float, delta: float, salary_accrued: float, deal_type: string}
     */
    public function calculateRealtime(array $data): array
    {
        $customerRate = (float) ($data['customer_rate'] ?? 0);
        $carrierRate = (float) ($data['carrier_rate'] ?? 0);
        $additionalExpenses = (float) ($data['additional_expenses'] ?? 0);
        $insurance = (float) ($data['insurance'] ?? 0);
        $bonus = (float) ($data['bonus'] ?? 0);
        $managerId = (int) ($data['manager_id'] ?? 0);
        $orderDate = $data['order_date'] ?? null;

        $dealType = $this->dealTypeClassifier->classify([
            'customer_payment_form' => $data['customer_payment_form'] ?? null,
            'carrier_payment_form' => $this->resolveCarrierPaymentFormForRealtime($data),
        ]);

        if ($dealType === 'unknown' || $orderDate === null || $managerId === 0) {
            return [
                'kpi_percent' => 0.0,
                'delta' => 0.0,
                'salary_accrued' => 0.0,
                'deal_type' => 'unknown',
            ];
        }

        // Get period stats for KPI calculation
        $period = $this->periodCalculator->getPeriodForDate($orderDate);
        $periodStats = $this->periodCalculator->getManagerPeriodStats($managerId, $period['start'], $period['end']);

        $kpiPercent = $this->kpiConfigurationService->resolveKpiPercentForDeal($dealType, $periodStats['direct_ratio']);
        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();
        $delta = $customerRate - ($customerRate * ($kpiPercent / 100)) - ($carrierRate + $additionalExpenses + $insurance + ($bonus * $bonusMultiplier));

        $salaryCoefficient = SalaryCoefficient::getForManagerOnDate($managerId, $orderDate);
        $salaryAccrued = $this->resolveSalaryAccrued($delta, $salaryCoefficient);

        return [
            'kpi_percent' => round($kpiPercent, 2),
            'delta' => round($delta, 2),
            'salary_accrued' => round($salaryAccrued, 2),
            'deal_type' => $dealType,
        ];
    }

    /**
     * Для превью в мастере: форма перевозчика из явного поля или из contractors_costs (одна форма либо mixed).
     */
    private function resolveCarrierPaymentFormForRealtime(array $data): ?string
    {
        if (filled($data['carrier_payment_form'] ?? null)) {
            return (string) $data['carrier_payment_form'];
        }

        $costs = $data['contractors_costs'] ?? null;
        if (! is_array($costs) || $costs === []) {
            return null;
        }

        $forms = collect($costs)
            ->pluck('payment_form')
            ->filter(fn ($v) => filled($v))
            ->map(fn ($v) => is_string($v) ? trim($v) : (string) $v)
            ->unique()
            ->values();

        if ($forms->isEmpty()) {
            return null;
        }

        return $forms->count() === 1 ? (string) $forms->first() : 'mixed';
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
            $financialTerm->client_payment_terms = PaymentScheduleSummaryFormatter::format(
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

        $invoiceByKey = $this->snapshotInvoiceNumbersByOrder($order->id);

        try {
            // Используем chunk для удаления, чтобы избежать ошибки 1615 Prepared statement needs to be re-prepared
            DB::table('payment_schedules')
                ->where('order_id', $order->id)
                ->orderBy('id')
                ->chunk(100, function ($schedules) {
                    DB::table('payment_schedules')
                        ->whereIn('id', $schedules->pluck('id')->toArray())
                        ->delete();
                });
        } catch (QueryException $e) {
            // Если возникает ошибка 1615, пытаемся переподключиться и удалить снова
            if (str_contains($e->getMessage(), '1615') || str_contains($e->getMessage(), 'Prepared statement needs to be re-prepared')) {
                // Закрываем текущее соединение и переподключаемся
                DB::purge('mysql');
                DB::reconnect('mysql');

                // Пытаемся удалить снова с chunk
                DB::table('payment_schedules')
                    ->where('order_id', $order->id)
                    ->orderBy('id')
                    ->chunk(100, function ($schedules) {
                        DB::table('payment_schedules')
                            ->whereIn('id', $schedules->pluck('id')->toArray())
                            ->delete();
                    });
            } else {
                throw $e;
            }
        }

        $paymentTerms = $this->decodePaymentTerms($order);
        $rows = [];

        $customerRows = $this->buildPaymentScheduleRows(
            $order,
            'customer',
            (float) ($order->customer_rate ?? 0),
            (array) data_get($paymentTerms, 'client.payment_schedule', []),
            null,
            $invoiceByKey,
        );

        foreach ($this->extractContractorsCosts($order) as $cost) {
            $carrierContractorId = isset($cost['contractor_id']) && $cost['contractor_id'] !== null && $cost['contractor_id'] !== ''
                ? (int) $cost['contractor_id']
                : null;

            $rows = [
                ...$rows,
                ...$this->buildPaymentScheduleRows(
                    $order,
                    'carrier',
                    (float) ($cost['amount'] ?? 0),
                    (array) ($cost['payment_schedule'] ?? []),
                    $carrierContractorId,
                    $invoiceByKey,
                ),
            ];
        }

        $rows = [...$customerRows, ...$rows];

        if ($rows === []) {
            return;
        }

        DB::table('payment_schedules')->insert($rows);

        PaymentScheduleAutomaticStatus::refreshForOrder($order->id);
    }

    /**
     * @param  array<string, mixed>  $schedule
     * @param  array<string, string>  $invoiceByKey
     * @return list<array<string, mixed>>
     */
    private function buildPaymentScheduleRows(
        Order $order,
        string $party,
        float $amount,
        array $schedule,
        ?int $carrierContractorId,
        array $invoiceByKey = [],
    ): array {
        if ($amount <= 0) {
            return [];
        }

        $rows = [];
        $hasPrepayment = (bool) ($schedule['has_prepayment'] ?? false);
        $prepaymentRatio = max(0, min(100, (float) ($schedule['prepayment_ratio'] ?? 0)));
        $prepaymentAmount = $hasPrepayment ? round($amount * ($prepaymentRatio / 100), 2) : 0.0;
        $finalAmount = round($amount - $prepaymentAmount, 2);

        if ($hasPrepayment && $prepaymentAmount > 0) {
            $rows[] = $this->paymentScheduleRowAttributes(
                $order,
                $party,
                'prepayment',
                $prepaymentAmount,
                $this->resolveScheduleDate(
                    $order,
                    $party,
                    (string) ($schedule['prepayment_mode'] ?? 'fttn'),
                    (int) ($schedule['prepayment_days'] ?? 0),
                    true,
                ),
                $carrierContractorId,
                $invoiceByKey,
            );
        }

        if ($finalAmount > 0) {
            $rows[] = $this->paymentScheduleRowAttributes(
                $order,
                $party,
                'final',
                $finalAmount,
                $this->resolveScheduleDate(
                    $order,
                    $party,
                    (string) ($schedule['postpayment_mode'] ?? 'ottn'),
                    (int) ($schedule['postpayment_days'] ?? 0),
                    false,
                ),
                $carrierContractorId,
                $invoiceByKey,
            );
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $invoiceByKey
     * @return array<string, mixed>
     */
    private function paymentScheduleRowAttributes(
        Order $order,
        string $party,
        string $type,
        float $amount,
        ?string $plannedDate,
        ?int $carrierContractorId,
        array $invoiceByKey = [],
    ): array {
        $row = [
            'order_id' => $order->id,
            'party' => $party,
            'type' => $type,
            'amount' => $amount,
            'planned_date' => $plannedDate,
            'actual_date' => null,
            'status' => 'pending',
            'notes' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn('payment_schedules', 'counterparty_id')) {
            $row['counterparty_id'] = $party === 'carrier' ? $carrierContractorId : null;
        }

        if (Schema::hasColumn('payment_schedules', 'invoice_number')) {
            $key = $party.'|'.$type.'|'.($plannedDate ?? '');
            $row['invoice_number'] = $invoiceByKey[$key] ?? null;
        }

        return $row;
    }

    /**
     * Сохраняем вручную введённые номера счетов при пересборке графика из мастера заказа.
     *
     * @return array<string, string>
     */
    private function snapshotInvoiceNumbersByOrder(int $orderId): array
    {
        if (! Schema::hasColumn('payment_schedules', 'invoice_number')) {
            return [];
        }

        $rows = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->get(['party', 'type', 'planned_date', 'invoice_number']);

        $map = [];
        foreach ($rows as $r) {
            $inv = trim((string) ($r->invoice_number ?? ''));
            if ($inv === '') {
                continue;
            }

            $key = $r->party.'|'.$r->type.'|'.($r->planned_date ?? '');
            $map[$key] = $r->invoice_number;
        }

        return $map;
    }

    private function resolveScheduleDate(
        Order $order,
        string $party,
        string $mode,
        int $days,
        bool $isPrepayment,
    ): ?string {
        $modeLower = strtolower((string) $mode);

        $baseDate = match ($modeLower) {
            'ottn' => $party === 'customer'
                ? $order->track_received_date_customer
                : $order->track_received_date_carrier,
            'fttn_receipt' => $isPrepayment
                ? $order->loading_date
                : $this->resolveFttnWithReceiptDate($order, $party),
            'fttn' => $isPrepayment
                ? $order->loading_date
                : $this->resolveFttnDate($order, $party),
            'loading' => $order->loading_date,
            'unloading' => $order->unloading_date,
            default => $isPrepayment ? $order->loading_date : $order->unloading_date,
        };

        if ($baseDate === null) {
            return null;
        }

        return $this->addWorkingDays(Carbon::parse($baseDate), max(0, $days))->toDateString();
    }

    private function resolveFttnDate(Order $order, string $party): ?string
    {
        $attachedAt = $this->orderDocumentRequirementService->paymentPackageAttachedAt($order, $party);

        return $attachedAt?->toDateString();
    }

    private function resolveFttnWithReceiptDate(Order $order, string $party): ?string
    {
        $attachedAt = $this->orderDocumentRequirementService->paymentPackageAttachedAt($order, $party);
        if ($attachedAt === null) {
            return null;
        }

        $receivedDate = $party === 'customer'
            ? $order->track_received_date_customer
            : $order->track_received_date_carrier;

        if ($receivedDate === null) {
            return null;
        }

        $receivedAt = Carbon::parse($receivedDate);

        return ($receivedAt->greaterThan($attachedAt) ? $receivedAt : $attachedAt)->toDateString();
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
}
