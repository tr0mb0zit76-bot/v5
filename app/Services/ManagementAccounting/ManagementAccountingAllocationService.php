<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementPayrollHalf;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\ManagementStatementLineSplit;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use App\Services\Finance\PaymentSchedulePaymentReversalService;
use App\Services\Finance\PaymentScheduleSettlementSyncService;
use App\Support\PaymentScheduleAutomaticStatus;
use App\Support\PaymentScheduleSettlementStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ManagementAccountingAllocationService
{
    public function __construct(
        private readonly PaymentSchedulePaymentLedgerService $paymentLedger,
        private readonly PaymentSchedulePaymentReversalService $paymentReversal,
        private readonly PaymentScheduleSettlementSyncService $settlementSync,
        private readonly ManagementPayrollHalfService $payrollHalfService,
        private readonly ManagementAccountingMatchingService $matching,
        private readonly ManagementOperationalCostCategoryResolver $costCategoryResolver,
    ) {}

    /**
     * @param  array{
     *     allocation_type: string,
     *     category_id?: ?int,
     *     payment_schedule_id?: ?int,
     *     user_id?: ?int,
     *     amount?: ?float,
     *     notes?: ?string,
     *     allocations?: list<array{payment_schedule_id: int, amount: float}>
     * }  $payload
     */
    public function allocateLine(ManagementStatementLine $line, array $payload, User $allocator): ManagementStatementLine
    {
        if (! empty($payload['allocations']) && is_array($payload['allocations'])) {
            return $this->allocateLineSplit($line, $payload, $allocator);
        }

        return DB::transaction(function () use ($line, $payload, $allocator): ManagementStatementLine {
            if ($line->status === 'allocated') {
                $this->reverseAllocatedLine($line, $allocator, 'Переразнесение на другую строку графика');
                $line->refresh();
            }

            $amount = round((float) ($payload['amount'] ?? $line->amount), 2);
            $allocationType = (string) ($payload['allocation_type'] ?? 'category');

            $line->allocation_amount = $amount;
            $line->allocated_by = $allocator->id;
            $line->allocated_at = now();
            $line->status = 'allocated';

            if ($allocationType === 'operational' && ! empty($payload['payment_schedule_id'])) {
                $schedule = PaymentSchedule::query()->findOrFail((int) $payload['payment_schedule_id']);
                $this->recordOperationalPayment($schedule, $line, $amount, $allocator);

                $line->allocation_payment_schedule_id = $schedule->id;
                $line->allocation_order_id = $schedule->order_id;
                $line->allocation_category_id = $this->categoryIdForParty(
                    (string) $schedule->party,
                    $schedule->order_id !== null ? (int) $schedule->order_id : null,
                    $schedule->counterparty_id !== null ? (int) $schedule->counterparty_id : null,
                );
                $line->match_type = 'operational';
            } elseif ($allocationType === 'payroll' && ! empty($payload['user_id'])) {
                $userId = (int) $payload['user_id'];
                $line->allocation_user_id = $userId;
                $line->allocation_category_id = $payload['category_id'] ?? $this->categoryIdByCode('payroll_managers');
                $line->match_type = 'payroll';

                $half = $this->payrollHalfService->ensureCurrentHalf(
                    CarbonImmutable::parse($line->operation_date),
                );
                $this->payrollHalfService->addPaidAmount(
                    ManagementPayrollHalf::query()->findOrFail($half['id']),
                    $userId,
                    $amount,
                );
            } else {
                $line->allocation_category_id = $payload['category_id'] ?? $line->suggested_category_id;
                $line->match_type = 'category';
            }

            $line->save();
            $this->refreshImportCounters($line->import_id);

            return $line->fresh([
                'allocationCategory',
                'allocationOrder',
                'allocationPaymentSchedule',
                'allocationUser',
            ]);
        });
    }

    /**
     * @param  array{
     *     allocation_type: string,
     *     allocations: list<array{payment_schedule_id: int, amount: float}>,
     *     notes?: ?string
     * }  $payload
     */
    private function allocateLineSplit(ManagementStatementLine $line, array $payload, User $allocator): ManagementStatementLine
    {
        return DB::transaction(function () use ($line, $payload, $allocator): ManagementStatementLine {
            if ((string) ($payload['allocation_type'] ?? '') !== 'operational') {
                throw new InvalidArgumentException('Разделение доступно только для операционного разнесения.');
            }

            $allocations = collect($payload['allocations'])
                ->map(fn (array $row): array => [
                    'payment_schedule_id' => (int) $row['payment_schedule_id'],
                    'amount' => round((float) $row['amount'], 2),
                ])
                ->filter(fn (array $row): bool => $row['payment_schedule_id'] > 0 && $row['amount'] > 0)
                ->values()
                ->all();

            if ($allocations === []) {
                throw new InvalidArgumentException('Укажите хотя бы одну строку графика для разнесения.');
            }

            $total = round(array_sum(array_column($allocations, 'amount')), 2);
            $lineAmount = round((float) $line->amount, 2);

            if (abs($total - $lineAmount) > 0.02) {
                throw new InvalidArgumentException('Сумма разнесения должна совпадать с суммой операции.');
            }

            if ($line->status === 'allocated') {
                $this->reverseAllocatedLine($line, $allocator, 'Переразнесение на несколько заявок');
                $line->refresh();
            }

            ManagementStatementLineSplit::query()
                ->where('management_statement_line_id', $line->id)
                ->delete();

            $line->allocation_amount = $lineAmount;
            $line->allocated_by = $allocator->id;
            $line->allocated_at = now();
            $line->status = 'allocated';
            $line->match_type = 'operational_split';
            $line->allocation_payment_schedule_id = null;
            $line->allocation_order_id = null;
            $line->allocation_category_id = null;
            $line->allocation_user_id = null;
            $line->save();

            foreach ($allocations as $allocation) {
                $schedule = PaymentSchedule::query()->findOrFail($allocation['payment_schedule_id']);
                $split = ManagementStatementLineSplit::query()->create([
                    'management_statement_line_id' => $line->id,
                    'allocation_type' => 'operational',
                    'payment_schedule_id' => $schedule->id,
                    'order_id' => $schedule->order_id,
                    'category_id' => $this->categoryIdForParty(
                        (string) $schedule->party,
                        $schedule->order_id !== null ? (int) $schedule->order_id : null,
                        $schedule->counterparty_id !== null ? (int) $schedule->counterparty_id : null,
                    ),
                    'amount' => $allocation['amount'],
                ]);

                $this->recordOperationalPayment(
                    $schedule,
                    $line,
                    $allocation['amount'],
                    $allocator,
                    'mgmt:'.$line->id.':'.$split->id,
                );
            }

            $firstSplit = ManagementStatementLineSplit::query()
                ->where('management_statement_line_id', $line->id)
                ->orderBy('id')
                ->first();

            if ($firstSplit !== null) {
                $line->allocation_payment_schedule_id = $firstSplit->payment_schedule_id;
                $line->allocation_order_id = $firstSplit->order_id;
                $line->allocation_category_id = $firstSplit->category_id;
                $line->save();
            }

            $this->refreshImportCounters($line->import_id);

            return $line->fresh([
                'allocationCategory',
                'allocationOrder',
                'allocationPaymentSchedule',
                'allocationUser',
                'splits.paymentSchedule',
                'splits.order',
            ]);
        });
    }

    public function deallocateLine(ManagementStatementLine $line, User $actor, ?string $reason = null): ManagementStatementLine
    {
        if ($line->status !== 'allocated') {
            throw new InvalidArgumentException('Операция не разнесена.');
        }

        return DB::transaction(function () use ($line, $actor, $reason): ManagementStatementLine {
            $this->reverseAllocatedLine($line, $actor, $reason);

            ManagementStatementLineSplit::query()
                ->where('management_statement_line_id', $line->id)
                ->delete();

            $importId = $line->import_id;

            $line->fill([
                'status' => 'pending',
                'match_type' => null,
                'allocation_amount' => null,
                'allocation_category_id' => null,
                'allocation_order_id' => null,
                'allocation_payment_schedule_id' => null,
                'allocation_user_id' => null,
                'allocated_by' => null,
                'allocated_at' => null,
            ]);

            $suggestion = $this->matching->suggestForLine($line);
            unset($suggestion['suggested_candidates']);
            $line->fill($suggestion)->save();

            $this->refreshImportCounters($importId);

            return $line->fresh([
                'allocationCategory',
                'allocationOrder',
                'allocationPaymentSchedule',
                'allocationUser',
            ]);
        });
    }

    private function reverseAllocatedLine(ManagementStatementLine $line, User $actor, ?string $reason = null): void
    {
        $matchType = (string) $line->match_type;
        $amount = round((float) ($line->allocation_amount ?? $line->amount), 2);

        if ($matchType === 'operational' || $matchType === 'operational_split') {
            $scheduleId = $line->allocation_payment_schedule_id !== null
                ? (int) $line->allocation_payment_schedule_id
                : null;

            $this->paymentReversal->reverseByManagementLineId(
                (int) $line->id,
                $actor,
                $reason ?? 'Отмена разнесения выписки',
                $scheduleId,
            );

            if ($scheduleId !== null) {
                $schedule = PaymentSchedule::query()->find($scheduleId);
                if ($schedule !== null) {
                    $this->settlementSync->syncRootSchedule($schedule);
                }
            }
        } elseif ($matchType === 'payroll' && $line->allocation_user_id !== null) {
            $half = $this->payrollHalfService->ensureCurrentHalf(
                CarbonImmutable::parse($line->operation_date),
            );
            $this->payrollHalfService->subtractPaidAmount(
                ManagementPayrollHalf::query()->findOrFail($half['id']),
                (int) $line->allocation_user_id,
                $amount,
            );
        }
    }

    public function createManualLine(array $payload, User $creator): ManagementStatementLine
    {
        $line = ManagementStatementLine::query()->create([
            'import_id' => null,
            'bank_account_id' => (int) $payload['bank_account_id'],
            'line_hash' => hash('sha256', 'manual|'.uniqid('', true)),
            'operation_date' => $payload['operation_date'],
            'direction' => $payload['direction'],
            'amount' => round((float) $payload['amount'], 2),
            'currency' => $payload['currency'] ?? 'RUB',
            'description' => $payload['description'],
            'status' => 'pending',
            'source' => 'manual',
            'created_by' => $creator->id,
        ]);

        $suggestion = $this->matching->suggestForLine($line);
        unset($suggestion['suggested_candidates']);
        $line->fill($suggestion)->save();

        return $line->fresh();
    }

    private function recordOperationalPayment(
        PaymentSchedule $schedule,
        ManagementStatementLine $line,
        float $amount,
        User $allocator,
        ?string $transactionReference = null,
    ): void {
        if (! Schema::hasColumn('payment_schedules', 'paid_amount')) {
            return;
        }

        $reference = $transactionReference ?? ('mgmt:'.$line->id);

        $paymentDate = $line->operation_date?->toDateString() ?? now()->toDateString();
        $scheduleAmount = (float) $schedule->amount;
        $paidAmount = (float) ($schedule->paid_amount ?? 0);
        $openBefore = PaymentScheduleSettlementStatus::outstandingAmount(
            $scheduleAmount,
            $paidAmount,
            $schedule->remaining_amount !== null ? (float) $schedule->remaining_amount : null,
        );
        $partialScheduleId = null;

        if ($paidAmount <= 0) {
            $schedule->paid_amount = round($amount, 2);
            $schedule->remaining_amount = max(0, round($openBefore - $amount, 2));
            $schedule->actual_date = $paymentDate;

            if (Schema::hasColumn('payment_schedules', 'payment_method')) {
                $schedule->payment_method = 'bank_transfer';
            }

            if (Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                $schedule->transaction_reference = $reference;
            }

            $schedule->status = $schedule->remaining_amount <= 0.009 ? 'paid' : 'pending';
            PaymentScheduleSettlementStatus::applyToSchedule($schedule);
            $schedule->save();
        } else {
            if (! Schema::hasColumn('payment_schedules', 'parent_payment_id')
                || ! Schema::hasColumn('payment_schedules', 'is_partial')) {
                return;
            }

            $partial = $schedule->replicate();
            $partial->amount = $amount;
            $partial->paid_amount = $amount;
            $partial->remaining_amount = 0;
            $partial->planned_date = $paymentDate;
            $partial->actual_date = $paymentDate;
            $partial->status = 'paid';
            $partial->parent_payment_id = $schedule->id;
            $partial->is_partial = true;

            if (Schema::hasColumn('payment_schedules', 'payment_method')) {
                $partial->payment_method = 'bank_transfer';
            }

            if (Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                $partial->transaction_reference = $reference;
            }

            $partial->save();
            $partialScheduleId = $partial->id;
            $schedule->paid_amount = round($paidAmount + $amount, 2);
            $schedule->remaining_amount = max(0, round($openBefore - $amount, 2));
            if ($schedule->remaining_amount <= 0.009) {
                $schedule->status = 'paid';
            } else {
                $schedule->status = 'pending';
            }
            PaymentScheduleSettlementStatus::applyToSchedule($schedule);
            $schedule->save();
        }

        $this->paymentLedger->recordFromPaymentSchedule(
            $schedule,
            $amount,
            $paymentDate,
            [
                'payment_method' => 'bank_transfer',
                'transaction_reference' => $reference,
                'notes' => 'Управленческий учёт: '.$line->description,
            ],
            $allocator->id,
            $partialScheduleId,
        );

        $schedule->refresh();
        $this->settlementSync->syncRootSchedule($schedule);

        PaymentScheduleAutomaticStatus::refreshForOrder((int) $schedule->order_id);
    }

    private function refreshImportCounters(?int $importId): void
    {
        if ($importId === null) {
            return;
        }

        $import = ManagementStatementImport::query()->find($importId);
        if ($import === null) {
            return;
        }

        $allocated = ManagementStatementLine::query()
            ->where('import_id', $importId)
            ->where('status', 'allocated')
            ->count();

        $import->update([
            'lines_allocated' => $allocated,
            'status' => $allocated >= $import->lines_count && $import->lines_count > 0 ? 'reconciled' : 'draft',
        ]);
    }

    private function categoryIdForParty(string $party, ?int $orderId = null, ?int $contractorId = null): ?int
    {
        if ($party === 'customer') {
            return $this->categoryIdByCode('operational_customer_in');
        }

        return $this->costCategoryResolver->categoryIdForCarrier($orderId, $contractorId)
            ?? $this->categoryIdByCode('operational_carrier_out');
    }

    private function categoryIdByCode(string $code): ?int
    {
        return ManagementExpenseCategory::query()->where('code', $code)->value('id');
    }
}
