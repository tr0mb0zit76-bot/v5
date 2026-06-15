<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementPayrollHalf;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\Finance\PaymentSchedulePaymentLedgerService;
use App\Services\Finance\PaymentSchedulePaymentReversalService;
use App\Support\PaymentScheduleAutomaticStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class ManagementAccountingAllocationService
{
    public function __construct(
        private readonly PaymentSchedulePaymentLedgerService $paymentLedger,
        private readonly PaymentSchedulePaymentReversalService $paymentReversal,
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
     *     notes?: ?string
     * }  $payload
     */
    public function allocateLine(ManagementStatementLine $line, array $payload, User $allocator): ManagementStatementLine
    {
        return DB::transaction(function () use ($line, $payload, $allocator): ManagementStatementLine {
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

    public function deallocateLine(ManagementStatementLine $line, User $actor, ?string $reason = null): ManagementStatementLine
    {
        if ($line->status !== 'allocated') {
            throw new InvalidArgumentException('Операция не разнесена.');
        }

        return DB::transaction(function () use ($line, $actor, $reason): ManagementStatementLine {
            $matchType = (string) $line->match_type;
            $amount = round((float) ($line->allocation_amount ?? $line->amount), 2);

            if ($matchType === 'operational') {
                $this->paymentReversal->reverseByManagementLineId(
                    (int) $line->id,
                    $actor,
                    $reason ?? 'Отмена разнесения выписки',
                    $line->allocation_payment_schedule_id !== null
                        ? (int) $line->allocation_payment_schedule_id
                        : null,
                );
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
    ): void {
        if (! Schema::hasColumn('payment_schedules', 'paid_amount')) {
            return;
        }

        $paymentDate = $line->operation_date?->toDateString() ?? now()->toDateString();
        $paidAmount = (float) ($schedule->paid_amount ?? 0);
        $remaining = (float) ($schedule->remaining_amount ?? max(0, (float) $schedule->amount - $paidAmount));
        $partialScheduleId = null;

        if ($paidAmount <= 0) {
            $schedule->paid_amount = $amount;
            $schedule->remaining_amount = max(0, $remaining - $amount);
            $schedule->actual_date = $paymentDate;

            if (Schema::hasColumn('payment_schedules', 'payment_method')) {
                $schedule->payment_method = 'bank_transfer';
            }

            if (Schema::hasColumn('payment_schedules', 'transaction_reference')) {
                $schedule->transaction_reference = 'mgmt:'.$line->id;
            }

            $schedule->status = $schedule->remaining_amount <= 0.009 ? 'paid' : 'pending';
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
                $partial->transaction_reference = 'mgmt:'.$line->id;
            }

            $partial->save();
            $partialScheduleId = $partial->id;
            $schedule->remaining_amount = max(0, $remaining - $amount);
            if ($schedule->remaining_amount <= 0.009) {
                $schedule->status = 'paid';
            }
            $schedule->save();
        }

        $this->paymentLedger->recordFromPaymentSchedule(
            $schedule,
            $amount,
            $paymentDate,
            [
                'payment_method' => 'bank_transfer',
                'transaction_reference' => 'mgmt:'.$line->id,
                'notes' => 'Управленческий учёт: '.$line->description,
            ],
            $allocator->id,
            $partialScheduleId,
        );

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
