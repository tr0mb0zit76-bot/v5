<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\FinancialTerm;
use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\SalaryCoefficient;
use App\Services\Finance\PaymentScheduleSettlementSyncService;
use App\Support\CalendarBankDayShifter;
use App\Support\CarrierRateFromFinancialTerms;
use App\Support\ContractorCostRowClassification;
use App\Support\OrderAdditionalCostNormalizer;
use App\Support\OrderCompensationSplitResolver;
use App\Support\OrderPaymentTermsConfigResolver;
use App\Support\OrderPersistedId;
use App\Support\OrderRouteMilestoneDateResolver;
use App\Support\PaymentInstallmentPlanner;
use App\Support\PaymentInstallmentScheduleNormalizer;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PaymentScheduleCashBasis;
use App\Support\PaymentSchedulePaymentEventRelinker;
use App\Support\PaymentScheduleSettlementPreserver;
use App\Support\PaymentScheduleSummaryFormatter;
use App\Support\PaymentTermsSummaryLimits;
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
            $this->refreshOrderCompensationFields($order);
            $this->syncPaymentSchedules($order);
        }
    }

    /**
     * Пересчитывает и сохраняет kpi_percent / delta / salary_accrued для одного заказа.
     */
    public function refreshOrderCompensationFields(Order $order): void
    {
        if (Schema::hasTable('financial_terms') && ! $order->relationLoaded('financialTerms')) {
            $order->load('financialTerms');
        }

        if (Schema::hasTable('order_documents') && ! $order->relationLoaded('documents')) {
            $order->load('documents');
        }

        $calculation = $this->calculateOrder($order);

        $payload = [
            'kpi_percent' => $calculation['kpi_percent'],
            'delta' => $calculation['delta'],
            'salary_accrued' => $calculation['salary_accrued'],
        ];

        if (Schema::hasColumn('orders', 'metadata') && isset($calculation['compensation_split_salary'])) {
            $metadata = is_array($order->metadata) ? $order->metadata : [];
            $payload['metadata'] = OrderCompensationSplitResolver::mergeSalaryIntoMetadata(
                $metadata,
                $calculation['compensation_split_salary'],
            );
        }

        $order->forceFill($payload)->saveQuietly();

        $this->syncFinancialTerms($order);
    }

    /**
     * @return array{kpi_percent: float, delta: float, salary_accrued: float, deal_type: string, period_info: array<string, int|float>}
     */
    public function calculateOrder(Order $order): array
    {
        $dealResolution = $this->dealTypeClassifier->resolve($order);
        $dealType = $dealResolution['deal_type'];

        if ($dealType === 'unknown' || $order->order_date === null) {
            return [
                'kpi_percent' => 0.0,
                'delta' => 0.0,
                'salary_accrued' => 0.0,
                'deal_type' => 'unknown',
                'period_info' => [],
            ];
        }

        $customerRate = (float) ($order->customer_rate ?? 0);
        $carrierRate = CarrierRateFromFinancialTerms::resolveForOrder($order);
        $additionalExpenses = (float) ($order->additional_expenses ?? 0);
        $insurance = (float) ($order->insurance ?? 0);
        $bonus = (float) ($order->bonus ?? 0);
        $expense = $this->buildMarginExpense($carrierRate, $additionalExpenses, $insurance, $bonus);
        $contractorsCosts = ContractorCostRowClassification::carrierLegCostsOnly(
            $this->extractContractorsCosts($order),
        );
        $kpiDeduction = $this->kpiConfigurationService->kpiDeductionAmountForResolution($customerRate, $dealResolution);
        $kpiPercent = $this->kpiConfigurationService->effectiveKpiPercentForResolution($customerRate, $dealResolution);
        $vatMarginSupplement = $this->kpiConfigurationService->marginSupplementForResolution(
            $dealResolution,
            (string) ($order->customer_payment_form ?? ''),
            $contractorsCosts,
        );
        $delta = $customerRate - $kpiDeduction - $expense + $vatMarginSupplement;

        $salaryCoefficient = SalaryCoefficient::getForManagerOnDate(
            (int) $order->manager_id,
            $order->order_date->toDateString(),
        );

        $totalSalary = $this->resolveSalaryAccrued($delta, $salaryCoefficient);
        $split = OrderCompensationSplitResolver::fromOrder($order);
        $salarySplit = OrderCompensationSplitResolver::applyToSalary($totalSalary, $split);

        return [
            'kpi_percent' => round($kpiPercent, 2),
            'delta' => round($delta, 2),
            'salary_accrued' => round($salarySplit['owner'], 2),
            'compensation_split_salary' => $salarySplit,
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

        $dealResolution = $this->dealTypeClassifier->resolve([
            'customer_payment_form' => $data['customer_payment_form'] ?? null,
            'carrier_payment_form' => $this->resolveCarrierPaymentFormForRealtime($data),
            'contractors_costs' => $contractorsCosts,
            'order_date' => $orderDate,
        ]);
        $dealType = $dealResolution['deal_type'];

        if ($dealType === 'unknown' || $orderDate === null || $managerId === 0) {
            return [
                'kpi_percent' => 0.0,
                'delta' => 0.0,
                'salary_accrued' => 0.0,
                'deal_type' => 'unknown',
            ];
        }

        $carrierLegCosts = ContractorCostRowClassification::carrierLegCostsOnly($contractorsCosts);
        $kpiDeduction = $this->kpiConfigurationService->kpiDeductionAmountForResolution($customerRate, $dealResolution);
        $kpiPercent = $this->kpiConfigurationService->effectiveKpiPercentForResolution($customerRate, $dealResolution);
        $expense = $this->buildMarginExpense($carrierRate, $additionalExpenses, $insurance, $bonus);
        $vatMarginSupplement = $this->kpiConfigurationService->marginSupplementForResolution(
            $dealResolution,
            isset($data['customer_payment_form']) ? (string) $data['customer_payment_form'] : null,
            $carrierLegCosts,
        );
        $delta = $customerRate - $kpiDeduction - $expense + $vatMarginSupplement;

        $salaryCoefficient = SalaryCoefficient::getForManagerOnDate($managerId, $orderDate);
        $totalSalary = $this->resolveSalaryAccrued($delta, $salaryCoefficient);
        $split = OrderCompensationSplitResolver::fromPayload($data);
        $salarySplit = OrderCompensationSplitResolver::applyToSalary($totalSalary, $split);

        return [
            'kpi_percent' => round($kpiPercent, 2),
            'delta' => round($delta, 2),
            'salary_accrued' => round($salarySplit['owner'], 2),
            'compensation_split_salary' => $salarySplit,
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
        if (! in_array($scenarioPaymentCategory, ['vat', 'vat_all', 'cash', 'vat_zero_22', 'vat_zero_cash'], true)) {
            throw new \InvalidArgumentException('scenarioPaymentCategory must be vat, vat_all, cash, vat_zero_22 or vat_zero_cash.');
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

        $dealResolution = $this->dealTypeClassifier->resolve([
            'customer_payment_form' => $data['customer_payment_form'] ?? null,
            'carrier_payment_form' => $data['carrier_payment_form'] ?? null,
            'contractors_costs' => $contractorsCosts,
            'order_date' => $orderDate,
        ]);

        if (! ($dealResolution['uses_custom_rules'] ?? false)) {
            $dealResolution = [
                'deal_type' => $scenarioPaymentCategory,
                'deal_type_label' => $scenarioPaymentCategory,
                'rule' => null,
                'uses_custom_rules' => false,
            ];
        }

        $carrierLegCosts = ContractorCostRowClassification::carrierLegCostsOnly($contractorsCosts);
        $kpiDeduction = $this->kpiConfigurationService->kpiDeductionAmountForResolution($customerRate, $dealResolution);
        $kpiPercent = $this->kpiConfigurationService->effectiveKpiPercentForResolution($customerRate, $dealResolution);
        $expense = $this->buildMarginExpense($carrierRate, $additionalExpenses, $insurance, $bonus);
        $vatMarginSupplement = $this->kpiConfigurationService->marginSupplementForResolution(
            $dealResolution,
            isset($data['customer_payment_form']) ? (string) $data['customer_payment_form'] : null,
            $carrierLegCosts,
        );
        $delta = $customerRate - $kpiDeduction - $expense + $vatMarginSupplement;

        $salaryCoefficient = SalaryCoefficient::getForManagerOnDate($managerId, $orderDate);
        $salaryAccrued = $this->resolveSalaryAccrued($delta, $salaryCoefficient);

        return [
            'kpi_percent' => round($kpiPercent, 2),
            'delta' => round($delta, 2),
            'salary_accrued' => round($salaryAccrued, 2),
            'deal_type' => (string) $dealResolution['deal_type'],
        ];
    }

    private function buildMarginExpense(
        float $carrierRate,
        float $additionalExpenses,
        float $insurance,
        float $bonus,
    ): float {
        $insuranceMultiplier = $this->kpiConfigurationService->getInsuranceMultiplier();
        $bonusMultiplier = $this->kpiConfigurationService->getBonusMultiplier();

        return $carrierRate
            + $additionalExpenses
            + ($insurance * $insuranceMultiplier)
            + ($bonus * $bonusMultiplier);
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

        $forms = collect(ContractorCostRowClassification::carrierLegCostsOnly($costs))
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
                $financialTerm->client_payment_terms = Str::limit($override, PaymentTermsSummaryLimits::MAX_LENGTH, '');
            } else {
                $fromOrderColumn = trim((string) ($order->customer_payment_term ?? ''));
                if ($fromOrderColumn !== '') {
                    $financialTerm->client_payment_terms = Str::limit($fromOrderColumn, PaymentTermsSummaryLimits::MAX_LENGTH, '');
                } else {
                    $existingFt = trim((string) ($financialTerm->client_payment_terms ?? ''));
                    if ($existingFt !== '') {
                        $financialTerm->client_payment_terms = Str::limit($existingFt, PaymentTermsSummaryLimits::MAX_LENGTH, '');
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
        $settlementSnapshot = app(PaymentScheduleSettlementPreserver::class)->snapshot($orderId);

        $paymentTerms = $this->decodePaymentTerms($order);
        $rows = [];

        $customerRows = $this->buildPaymentScheduleRows(
            $order,
            'customer',
            (float) ($order->customer_rate ?? 0),
            OrderPaymentTermsConfigResolver::resolveClientPaymentSchedule($order),
            null,
            $invoiceByKey,
            null,
            filled($order->customer_payment_form) ? (string) $order->customer_payment_form : null,
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
                    filled($cost['payment_form'] ?? null) ? (string) $cost['payment_form'] : null,
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
                    filled($additionalCost['payment_form'] ?? null) ? (string) $additionalCost['payment_form'] : null,
                ),
            ];
        }

        $rows = [...$customerRows, ...$rows];

        if ($rows === []) {
            return;
        }

        DB::transaction(function () use ($orderId, $rows, $settlementSnapshot): void {
            $this->deletePaymentSchedulesForOrder($orderId);

            DB::table('payment_schedules')->insert($rows);

            app(PaymentScheduleSettlementPreserver::class)->restore($orderId, $settlementSnapshot);

            app(PaymentSchedulePaymentEventRelinker::class)->relinkOrphanedEventsForOrder($orderId);
            $this->syncPaymentScheduleSettlementForOrder($orderId);

            PaymentScheduleAutomaticStatus::refreshForOrder($orderId);
        });
    }

    private function deletePaymentSchedulesForOrder(int $orderId): void
    {
        try {
            // Используем chunk для удаления, чтобы избежать ошибки 1615 Prepared statement needs to be re-prepared
            DB::table('payment_schedules')
                ->where('order_id', $orderId)
                ->orderBy('id')
                ->chunk(100, function ($schedules): void {
                    DB::table('payment_schedules')
                        ->whereIn('id', $schedules->pluck('id')->toArray())
                        ->delete();
                });
        } catch (QueryException $e) {
            // Если возникает ошибка 1615, пытаемся переподключиться и удалить снова
            if (str_contains($e->getMessage(), '1615') || str_contains($e->getMessage(), 'Prepared statement needs to be re-prepared')) {
                DB::purge('mysql');
                DB::reconnect('mysql');

                DB::table('payment_schedules')
                    ->where('order_id', $orderId)
                    ->orderBy('id')
                    ->chunk(100, function ($schedules): void {
                        DB::table('payment_schedules')
                            ->whereIn('id', $schedules->pluck('id')->toArray())
                            ->delete();
                    });

                return;
            }

            throw $e;
        }
    }

    private function syncPaymentScheduleSettlementForOrder(int $orderId): void
    {
        $sync = app(PaymentScheduleSettlementSyncService::class);
        if (! $sync->ledgerTableExists() || ! Schema::hasColumn('payment_schedules', 'paid_amount')) {
            return;
        }

        $query = PaymentSchedule::query()->where('order_id', $orderId);

        if (Schema::hasColumn('payment_schedules', 'parent_payment_id')) {
            $query->whereNull('parent_payment_id');
        }

        if (Schema::hasColumn('payment_schedules', 'is_partial')) {
            $query->where(function ($q): void {
                $q->whereNull('is_partial')->orWhere('is_partial', false);
            });
        }

        foreach ($query->cursor() as $schedule) {
            $sync->syncRootSchedule($schedule);
        }
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
        ?string $paymentForm = null,
    ): array {
        if ($amount <= 0) {
            return [];
        }

        $schedule = PaymentInstallmentScheduleNormalizer::normalize(
            PaymentInstallmentScheduleNormalizer::ensureInstallmentModel($schedule),
            $amount,
        );

        return $this->buildInstallmentPaymentScheduleRows(
            $order,
            $party,
            $amount,
            $schedule,
            $carrierContractorId,
            $invoiceByKey,
            $scheduleOrderDate,
            $paymentForm,
        );
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
        ?string $paymentForm = null,
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
            $planned = $this->plannedDateForInstallmentRow($order, $party, $row, $ctx, $paymentForm);
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
                $slot,
            );
        }

        return $rows;
    }

    /**
     * Колонка payment_schedules.type: для двух траншей — prepayment/final, иначе installment.
     */
    private function paymentScheduleTypeForInstallmentSlot(int $slot, int $totalSlots): string
    {
        if ($totalSlots <= 1) {
            return 'final';
        }

        if ($totalSlots === 2) {
            return $slot === 1 ? 'prepayment' : 'final';
        }

        return 'installment';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, ?string>  $contextDates
     */
    private function plannedDateForInstallmentRow(
        Order $order,
        string $party,
        array $row,
        array $contextDates,
        ?string $paymentForm = null,
    ): ?string {
        $basis = PaymentScheduleCashBasis::effectiveBasis(
            $paymentForm ?? $this->paymentFormForParty($order, $party),
            (string) ($row['basis'] ?? 'fttn'),
        );
        $offsetDays = (int) ($row['offset_days'] ?? 0);
        $offsetUnit = (string) ($row['offset_unit'] ?? CalendarBankDayShifter::UNIT_CALENDAR);

        if (in_array($basis, ['fttn', 'fttn_receipt', 'ottn', 'loading', 'unloading'], true)) {
            $eventDate = $this->resolveScheduleDate($order, $party, $basis, 0, false);
            if ($eventDate === null) {
                return null;
            }

            return CalendarBankDayShifter::shift(
                Carbon::parse($eventDate),
                $offsetDays,
                $offsetUnit,
            )->toDateString();
        }

        return PaymentInstallmentPlanner::plannedDateForInstallment($row, $order, $contextDates);
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
        ?int $installmentSequence = null,
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

        if (Schema::hasColumn('payment_schedules', 'installment_sequence')) {
            $row['installment_sequence'] = $installmentSequence;
        }

        if (Schema::hasColumn('payment_schedules', 'invoice_number')) {
            $key = $this->paymentScheduleInvoiceKey($party, $type, $plannedDate, $installmentSequence);
            $row['invoice_number'] = $invoiceByKey[$key] ?? null;
        }

        return $row;
    }

    private function paymentScheduleInvoiceKey(string $party, string $type, ?string $plannedDate, ?int $installmentSequence): string
    {
        return $party.'|'.$type.'|'.($plannedDate ?? '').'|'.($installmentSequence ?? 0);
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

        $columns = ['party', 'type', 'planned_date', 'invoice_number'];
        if (Schema::hasColumn('payment_schedules', 'installment_sequence')) {
            $columns[] = 'installment_sequence';
        }

        $rows = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->get($columns);

        $map = [];
        foreach ($rows as $r) {
            $inv = trim((string) ($r->invoice_number ?? ''));
            if ($inv === '') {
                continue;
            }

            $sequence = Schema::hasColumn('payment_schedules', 'installment_sequence')
                ? (isset($r->installment_sequence) ? (int) $r->installment_sequence : null)
                : null;
            $key = $this->paymentScheduleInvoiceKey(
                (string) $r->party,
                (string) $r->type,
                $r->planned_date !== null ? (string) $r->planned_date : null,
                $sequence,
            );
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
                ? OrderRouteMilestoneDateResolver::resolveLoadingDate($order)
                : $this->resolveFttnWithReceiptDate($order, $party),
            'fttn' => $isPrepayment
                ? OrderRouteMilestoneDateResolver::resolveLoadingDate($order)
                : $this->resolveFttnDate($order, $party),
            'loading' => OrderRouteMilestoneDateResolver::resolveLoadingDate($order),
            'unloading' => OrderRouteMilestoneDateResolver::resolveUnloadingDate($order),
            default => $isPrepayment
                ? OrderRouteMilestoneDateResolver::resolveLoadingDate($order)
                : OrderRouteMilestoneDateResolver::resolveUnloadingDate($order),
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

    private function paymentFormForParty(Order $order, string $party): ?string
    {
        if ($party === 'customer') {
            return filled($order->customer_payment_form)
                ? (string) $order->customer_payment_form
                : null;
        }

        $costs = $this->extractContractorsCosts($order);

        foreach ($costs as $cost) {
            $contractorId = isset($cost['contractor_id']) && $cost['contractor_id'] !== null && $cost['contractor_id'] !== ''
                ? (int) $cost['contractor_id']
                : null;

            if ($contractorId === null) {
                continue;
            }

            if (filled($cost['payment_form'] ?? null)) {
                if ($party === 'carrier' && (int) ($order->carrier_id ?? 0) === $contractorId) {
                    return (string) $cost['payment_form'];
                }

                if ($party === 'contractor') {
                    return (string) $cost['payment_form'];
                }
            }
        }

        return filled($order->carrier_payment_form) ? (string) $order->carrier_payment_form : null;
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
