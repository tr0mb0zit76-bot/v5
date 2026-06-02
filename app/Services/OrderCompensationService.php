<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\SalaryCoefficient;
use App\Support\CarrierRateFromFinancialTerms;
use App\Support\OrderAdditionalCostNormalizer;
use App\Support\OrderPaymentTermsConfigResolver;
use App\Support\OrderPersistedId;
use App\Support\PaymentInstallmentPlanner;
use App\Support\PaymentInstallmentScheduleNormalizer;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PaymentScheduleSummaryFormatter;
use App\Support\VatZeroCustomerStandardVatCarrierMarginSupplement;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrderCompensationService
{
    public function __construct(
        private readonly DealTypeClassifier $dealTypeClassifier,
        private readonly PeriodCalculator $periodCalculator,
        private readonly KpiConfigurationService $kpiConfigurationService,
        private readonly OrderDocumentRequirementService $orderDocumentRequirementService,
    ) {}

    public function resyncPaymentSchedulesForOrder(Order $order): void
    {
        $this->syncPaymentSchedules($order);
    }

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

        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();
        $customerRate = (float) ($order->customer_rate ?? 0);
        $carrierRate = CarrierRateFromFinancialTerms::resolveForOrder($order);
        $additionalExpenses = (float) ($order->additional_expenses ?? 0);
        $insurance = (float) ($order->insurance ?? 0);
        $bonus = (float) ($order->bonus ?? 0);
        $expense = $carrierRate + $additionalExpenses + $insurance + ($bonus * $bonusMultiplier);
        $contractorsCosts = $this->extractContractorsCosts($order);
        $kpiDeduction = $this->kpiConfigurationService->kpiDeductionAmount($customerRate, $dealType);
        $kpiPercent = $this->kpiConfigurationService->effectiveKpiPercent($customerRate, $dealType);
        $vatMarginSupplement = VatZeroCustomerStandardVatCarrierMarginSupplement::amount(
            (string) ($order->customer_payment_form ?? ''),
            $contractorsCosts,
        );
        $delta = $customerRate - $kpiDeduction - $expense + $vatMarginSupplement;

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
        $contractorsCosts = is_array($data['contractors_costs'] ?? null) ? $data['contractors_costs'] : [];

        $dealType = $this->dealTypeClassifier->classify([
            'customer_payment_form' => $data['customer_payment_form'] ?? null,
            'carrier_payment_form' => $this->resolveCarrierPaymentFormForRealtime($data),
            'contractors_costs' => $contractorsCosts,
        ]);

        if ($dealType === 'unknown' || $orderDate === null || $managerId === 0) {
            return [
                'kpi_percent' => 0.0,
                'delta' => 0.0,
                'salary_accrued' => 0.0,
                'deal_type' => 'unknown',
            ];
        }

        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();
        $kpiDeduction = $this->kpiConfigurationService->kpiDeductionAmount($customerRate, $dealType);
        $kpiPercent = $this->kpiConfigurationService->effectiveKpiPercent($customerRate, $dealType);
        $expense = $carrierRate + $additionalExpenses + $insurance + ($bonus * $bonusMultiplier);
        $vatMarginSupplement = VatZeroCustomerStandardVatCarrierMarginSupplement::amount(
            isset($data['customer_payment_form']) ? (string) $data['customer_payment_form'] : null,
            $contractorsCosts,
        );
        $delta = $customerRate - $kpiDeduction - $expense + $vatMarginSupplement;

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
     * Считалка: KPI и маржа для сценария безнал или наличка.
     *
     * @param  array{
     *     customer_rate?: float,
     *     carrier_rate?: float,
     *     additional_expenses?: float,
     *     insurance?: float,
     *     bonus?: float,
     *     manager_id?: int,
     *     order_date?: string|null,
     *     customer_payment_form?: string|null,
     *     carrier_payment_form?: string|null,
     *     contractors_costs?: list<array<string, mixed>>,
     * }  $data
     * @return array{
     *     kpi_percent: float,
     *     delta: float,
     *     salary_accrued: float,
     *     deal_type: string,
     * }
     */
    public function calculateMarginScenario(array $data, string $scenarioPaymentCategory): array
    {
        if (! in_array($scenarioPaymentCategory, ['vat', 'cash', 'vat_zero_22'], true)) {
            throw new \InvalidArgumentException('scenarioPaymentCategory must be vat, cash or vat_zero_22.');
        }

        $customerRate = (float) ($data['customer_rate'] ?? 0);
        $carrierRate = (float) ($data['carrier_rate'] ?? 0);
        $additionalExpenses = (float) ($data['additional_expenses'] ?? 0);
        $insurance = (float) ($data['insurance'] ?? 0);
        $bonus = (float) ($data['bonus'] ?? 0);
        $managerId = (int) ($data['manager_id'] ?? 0);
        $orderDate = $data['order_date'] ?? null;
        $contractorsCosts = is_array($data['contractors_costs'] ?? null) ? $data['contractors_costs'] : [];

        if ($orderDate === null || $managerId === 0) {
            return [
                'kpi_percent' => 0.0,
                'delta' => 0.0,
                'salary_accrued' => 0.0,
                'deal_type' => 'unknown',
            ];
        }

        $kpiDeduction = $this->kpiConfigurationService->kpiDeductionAmount($customerRate, $scenarioPaymentCategory);
        $kpiPercent = $this->kpiConfigurationService->effectiveKpiPercent($customerRate, $scenarioPaymentCategory);
        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();
        $expense = $carrierRate + $additionalExpenses + $insurance + ($bonus * $bonusMultiplier);
        $vatMarginSupplement = VatZeroCustomerStandardVatCarrierMarginSupplement::amount(
            isset($data['customer_payment_form']) ? (string) $data['customer_payment_form'] : null,
            $contractorsCosts,
        );
        $delta = $customerRate - $kpiDeduction - $expense + $vatMarginSupplement;

        $salaryCoefficient = SalaryCoefficient::getForManagerOnDate($managerId, $orderDate);
        $salaryAccrued = $this->resolveSalaryAccrued($delta, $salaryCoefficient);

        return [
            'kpi_percent' => round($kpiPercent, 2),
            'delta' => round($delta, 2),
            'salary_accrued' => round($salaryAccrued, 2),
            'deal_type' => $scenarioPaymentCategory,
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

        $orderId = OrderPersistedId::resolve($order);
        if ($orderId === null) {
            return;
        }

        $financialTerm = FinancialTerm::query()->firstOrNew([
            'order_id' => $orderId,
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
            $override = trim((string) data_get($paymentTerms, 'client.payment_terms_text', ''));
            if ($override !== '') {
                $financialTerm->client_payment_terms = Str::limit($override, 255, '');
            } else {
                $fromOrderColumn = trim((string) ($order->customer_payment_term ?? ''));
                if ($fromOrderColumn !== '') {
                    $financialTerm->client_payment_terms = Str::limit($fromOrderColumn, 255, '');
                } else {
                    $existingFt = trim((string) ($financialTerm->client_payment_terms ?? ''));
                    if ($existingFt !== '') {
                        $financialTerm->client_payment_terms = Str::limit($existingFt, 255, '');
                    } else {
                        $order->loadMissing(['legs.routePoints']);
                        $financialTerm->client_payment_terms = PaymentScheduleSummaryFormatter::format(
                            (array) data_get($paymentTerms, 'client.payment_schedule', []),
                            (float) ($order->customer_rate ?? 0),
                            (string) ($financialTerm->client_currency ?: 'RUB'),
                            $order,
                            [],
                        );
                    }
                }
            }
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

        $orderId = OrderPersistedId::resolve($order);
        if ($orderId === null) {
            return;
        }

        $invoiceByKey = $this->snapshotInvoiceNumbersByOrder($orderId);

        try {
            // Используем chunk для удаления, чтобы избежать ошибки 1615 Prepared statement needs to be re-prepared
            DB::table('payment_schedules')
                ->where('order_id', $orderId)
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
                    ->where('order_id', $orderId)
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
            OrderPaymentTermsConfigResolver::resolveClientPaymentSchedule($order),
            null,
            $invoiceByKey,
        );

        $contractorCosts = $this->extractContractorsCosts($order);
        $contractorTypeById = collect($contractorCosts)
            ->pluck('contractor_id')
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->whenNotEmpty(fn ($ids) => Contractor::query()->whereIn('id', $ids->all())->pluck('type', 'id'))
            ->mapWithKeys(fn (mixed $type, mixed $id): array => [(int) $id => (string) $type]);

        foreach ($contractorCosts as $cost) {
            $carrierContractorId = isset($cost['contractor_id']) && $cost['contractor_id'] !== null && $cost['contractor_id'] !== ''
                ? (int) $cost['contractor_id']
                : null;
            $counterpartyType = $carrierContractorId !== null
                ? ($contractorTypeById->get($carrierContractorId) ?? null)
                : null;
            $scheduleParty = $counterpartyType === 'contractor' ? 'contractor' : 'carrier';

            $scheduleOrderDate = null;
            if (! empty($cost['is_additional'])) {
                $scheduleOrderDate = filled($cost['incurred_date'] ?? null)
                    ? (string) $cost['incurred_date']
                    : optional($order->additional_expenses_payment_date)?->toDateString();
            }

            $rows = [
                ...$rows,
                ...$this->buildPaymentScheduleRows(
                    $order,
                    $scheduleParty,
                    (float) ($cost['amount'] ?? 0),
                    (array) ($cost['payment_schedule'] ?? []),
                    $carrierContractorId,
                    $invoiceByKey,
                    $scheduleOrderDate,
                ),
            ];
        }

        $order->loadMissing('financialTerms');
        $financialTerm = $order->financialTerms->first();
        $additionalCosts = is_array($financialTerm?->additional_costs) ? $financialTerm->additional_costs : [];

        foreach (OrderAdditionalCostNormalizer::normalizeList($additionalCosts) as $additionalCost) {
            $contractorId = $additionalCost['contractor_id'] ?? null;

            if ($contractorId === null) {
                continue;
            }

            $rows = [
                ...$rows,
                ...$this->buildPaymentScheduleRows(
                    $order,
                    'contractor',
                    (float) ($additionalCost['amount'] ?? 0),
                    (array) ($additionalCost['payment_schedule'] ?? []),
                    (int) $contractorId,
                    $invoiceByKey,
                    filled($additionalCost['service_date'] ?? null) ? (string) $additionalCost['service_date'] : null,
                ),
            ];
        }

        $rows = [...$customerRows, ...$rows];

        if ($rows === []) {
            return;
        }

        DB::table('payment_schedules')->insert($rows);

        PaymentScheduleAutomaticStatus::refreshForOrder($orderId);
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
        ?string $scheduleOrderDate = null,
    ): array {
        if ($amount <= 0) {
            return [];
        }

        if (PaymentInstallmentScheduleNormalizer::isInstallmentModel($schedule)) {
            $schedule = PaymentInstallmentScheduleNormalizer::normalize($schedule, $amount);

            return $this->buildInstallmentPaymentScheduleRows(
                $order,
                $party,
                $amount,
                $schedule,
                $carrierContractorId,
                $invoiceByKey,
                $scheduleOrderDate,
            );
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
     * @param  array<string, mixed>  $schedule
     * @param  array<string, string>  $invoiceByKey
     * @return list<array<string, mixed>>
     */
    private function buildInstallmentPaymentScheduleRows(
        Order $order,
        string $party,
        float $amount,
        array $schedule,
        ?int $carrierContractorId,
        array $invoiceByKey = [],
        ?string $scheduleOrderDate = null,
    ): array {
        $order->loadMissing(['legs.routePoints']);
        $ctx = PaymentInstallmentPlanner::dateContextFromOrder($order);
        if (filled($scheduleOrderDate)) {
            $ctx['order_date'] = $scheduleOrderDate;
        }

        /** @var list<array<string, mixed>> $installments */
        $installments = array_values(array_filter($schedule['installments'] ?? [], static fn ($r): bool => is_array($r)));
        $rows = [];

        $installmentCount = count($installments);

        foreach ($installments as $index => $row) {
            $slot = $index + 1;
            $planned = PaymentInstallmentPlanner::plannedDateForInstallment($row, $order, $ctx);
            $partAmount = round((float) ($row['amount'] ?? 0), 2);
            if ($partAmount <= 0) {
                continue;
            }

            $rows[] = $this->paymentScheduleRowAttributes(
                $order,
                $party,
                $this->paymentScheduleTypeForInstallmentSlot($slot, $installmentCount),
                $partAmount,
                $planned,
                $carrierContractorId,
                $invoiceByKey,
            );
        }

        return $rows;
    }

    /**
     * Колонка payment_schedules.type — enum('prepayment', 'final').
     */
    private function paymentScheduleTypeForInstallmentSlot(int $slot, int $totalSlots): string
    {
        if ($totalSlots <= 1) {
            return 'final';
        }

        return $slot === 1 ? 'prepayment' : 'final';
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
        $orderId = OrderPersistedId::resolveOrFail($order);

        $row = [
            'order_id' => $orderId,
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
            $row['counterparty_id'] = in_array($party, ['carrier', 'contractor'], true) ? $carrierContractorId : null;
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
        return OrderPaymentTermsConfigResolver::forSync($order);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractContractorsCosts(Order $order): array
    {
        if (! Schema::hasTable('financial_terms')) {
            return [];
        }

        $orderId = OrderPersistedId::resolve($order);
        if ($orderId === null) {
            return [];
        }

        $financialTerm = FinancialTerm::query()
            ->where('order_id', $orderId)
            ->first();

        return is_array($financialTerm?->contractors_costs) ? $financialTerm->contractors_costs : [];
    }
}
