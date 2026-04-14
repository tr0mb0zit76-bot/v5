<?php

namespace App\Services;

use App\Models\SalaryAccrual;
use App\Models\SalaryCoefficient;
use App\Models\SalaryPayout;
use App\Models\SalaryPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class SalaryPayrollService
{
    public function createPeriod(array $payload, ?int $userId): SalaryPeriod
    {
        return SalaryPeriod::query()->create([
            'period_start' => $payload['period_start'],
            'period_end' => $payload['period_end'],
            'period_type' => $payload['period_type'],
            'status' => 'draft',
            'notes' => $payload['notes'] ?? null,
            'created_by' => $userId,
        ]);
    }

    public function recalculatePeriod(SalaryPeriod $period): void
    {
        DB::transaction(function () use ($period): void {
            $hasLockedPayouts = SalaryPayout::query()
                ->where('period_id', $period->id)
                ->where('type', '!=', 'advance')
                ->exists();
            $hasAllocatedPayouts = DB::table('salary_payout_allocations')
                ->join('salary_payouts', 'salary_payouts.id', '=', 'salary_payout_allocations.payout_id')
                ->where('salary_payouts.period_id', $period->id)
                ->exists();

            if ($hasLockedPayouts || $hasAllocatedPayouts) {
                throw new RuntimeException('Нельзя пересчитать период с уже проведёнными выплатами.');
            }

            SalaryAccrual::query()->where('period_id', $period->id)->delete();

            if (! Schema::hasTable('orders')) {
                return;
            }

            $orders = DB::table('orders')
                ->when(
                    Schema::hasColumn('orders', 'deleted_at'),
                    fn ($query) => $query->whereNull('orders.deleted_at')
                )
                ->whereNotNull('orders.manager_id')
                ->whereDate('orders.order_date', '>=', $period->period_start)
                ->whereDate('orders.order_date', '<=', $period->period_end)
                ->select([
                    'orders.id',
                    'orders.manager_id',
                    'orders.order_date',
                    'orders.delta',
                    'orders.kpi_percent',
                    'orders.salary_accrued',
                    'orders.customer_rate',
                ])
                ->get();

            foreach ($orders as $order) {
                $orderDate = (string) $order->order_date;
                $delta = (float) ($order->delta ?? 0);
                $salaryCoefficient = SalaryCoefficient::getForManagerOnDate((int) $order->manager_id, $orderDate);
                $salaryAmount = round((float) ($order->salary_accrued ?? 0), 2);

                if ($salaryAmount === 0.0) {
                    $salaryAmount = $this->fallbackSalaryAmount($delta, $salaryCoefficient);
                }

                $customerRate = (float) ($order->customer_rate ?? 0);
                $paidAtAccrual = $this->paidCustomerAmountForOrderUntil((int) $order->id, $period->period_end->toDateString());
                $isCustomerFullyPaid = $this->isCustomerFullyPaid($customerRate, $paidAtAccrual);
                $payableAtAccrual = $isCustomerFullyPaid ? $salaryAmount : 0.0;

                SalaryAccrual::query()->create([
                    'period_id' => $period->id,
                    'user_id' => (int) $order->manager_id,
                    'order_id' => (int) $order->id,
                    'order_date_snapshot' => $order->order_date,
                    'delta_snapshot' => $delta,
                    'salary_amount' => $salaryAmount,
                    'customer_rate_snapshot' => $customerRate,
                    'paid_customer_amount_at_accrual' => $paidAtAccrual,
                    'payable_amount_computed' => $payableAtAccrual,
                    'paid_amount_fact' => 0,
                    'unpaid_amount' => $salaryAmount,
                    'meta' => [
                        'calculation_mode' => $salaryCoefficient === null ? 'kpi' : 'base_plus_margin_percent',
                        'kpi_percent_snapshot' => (float) ($order->kpi_percent ?? 0),
                        'base_salary_snapshot' => (float) ($salaryCoefficient?->base_salary ?? 0),
                        'bonus_percent_snapshot' => (float) ($salaryCoefficient?->bonus_percent ?? 0),
                        'customer_fully_paid_at_accrual' => $isCustomerFullyPaid,
                    ],
                ]);
            }

            $this->settleOpenAdvancesForPeriod($period);
        });
    }

    public function createPayout(SalaryPeriod $period, array $payload, ?int $createdBy): SalaryPayout
    {
        return DB::transaction(function () use ($period, $payload, $createdBy): SalaryPayout {
            $type = $payload['type'] ?? 'salary';

            $payout = SalaryPayout::query()->create([
                'period_id' => $period->id,
                'user_id' => (int) $payload['user_id'],
                'amount' => (float) $payload['amount'],
                'payout_date' => $payload['payout_date'],
                'type' => $type,
                'comment' => $payload['comment'] ?? null,
                'created_by' => $createdBy,
            ]);

            $amountLeft = (float) $payload['amount'];
            $accruals = SalaryAccrual::query()
                ->where('user_id', (int) $payload['user_id'])
                ->orderBy('order_date_snapshot')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            /** @var SalaryAccrual $accrual */
            foreach ($accruals as $accrual) {
                if ($amountLeft <= 0) {
                    break;
                }

                $available = $this->availableToAllocateForAccrual($accrual, $period);
                if ($available <= 0) {
                    continue;
                }

                $portion = min($amountLeft, $available);
                $payout->allocations()->create([
                    'accrual_id' => $accrual->id,
                    'amount' => $portion,
                ]);

                $this->refreshAccrualPaymentTotals($accrual);
                $amountLeft = round($amountLeft - $portion, 2);
            }

            if ($type === 'salary' && $amountLeft > 0.009) {
                throw new RuntimeException('Сумма выплаты превышает доступную сумму к выплате.');
            }

            return $payout;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function userSummariesForPeriod(?SalaryPeriod $period, ?int $userId = null): array
    {
        if ($period === null) {
            return [];
        }

        $userRows = SalaryAccrual::query()
            ->leftJoin('users', 'users.id', '=', 'salary_accruals.user_id')
            ->when($userId !== null, fn ($query) => $query->where('salary_accruals.user_id', $userId))
            ->select('salary_accruals.user_id', 'users.name as user_name')
            ->groupBy('salary_accruals.user_id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return $userRows->map(function (object $row) use ($period): array {
            $userId = (int) $row->user_id;
            $periodAccruals = SalaryAccrual::query()
                ->where('period_id', $period->id)
                ->where('user_id', $userId)
                ->get();
            $allAccruals = SalaryAccrual::query()
                ->where('user_id', $userId)
                ->orderBy('order_date_snapshot')
                ->get();
            $payableTotal = $allAccruals->sum(
                fn (SalaryAccrual $accrual): float => $this->payableForAccrualToPeriodEnd($accrual, $period)
            );
            $allocatedTotal = $allAccruals->sum(
                fn (SalaryAccrual $accrual): float => $this->allocatedAmountForAccrualTotal($accrual->id)
            );
            $paidTotal = (float) SalaryPayout::query()
                ->where('period_id', $period->id)
                ->where('user_id', $userId)
                ->sum('amount');
            $advanceTotal = (float) SalaryPayout::query()
                ->where('period_id', $period->id)
                ->where('user_id', $userId)
                ->where('type', 'advance')
                ->sum('amount');
            $advanceBalance = $this->unallocatedAdvanceAmountForUserUntil($userId, $period->period_end->toDateString());

            return [
                'user_id' => $userId,
                'user_name' => $row->user_name,
                'accrued_total' => round((float) $periodAccruals->sum('salary_amount'), 2),
                'payable_total' => round($payableTotal, 2),
                'paid_total' => round($paidTotal, 2),
                'advance_total' => round($advanceTotal, 2),
                'advance_balance' => round($advanceBalance, 2),
                'unpaid_total' => round(max(0.0, $allAccruals->sum(fn (SalaryAccrual $accrual): float => (float) $accrual->unpaid_amount)), 2),
                'payable_left' => round(max(0.0, $payableTotal - $allocatedTotal), 2),
            ];
        })->values()->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function orderRowsForPeriod(?SalaryPeriod $period, ?int $userId = null): array
    {
        if ($period === null) {
            return [];
        }

        $rows = SalaryAccrual::query()
            ->leftJoin('orders', 'orders.id', '=', 'salary_accruals.order_id')
            ->leftJoin('users', 'users.id', '=', 'salary_accruals.user_id')
            ->when($userId !== null, fn ($query) => $query->where('salary_accruals.user_id', $userId))
            ->select([
                'salary_accruals.id',
                'salary_accruals.period_id',
                'salary_accruals.order_id',
                'salary_accruals.user_id',
                'salary_accruals.salary_amount',
                'salary_accruals.customer_rate_snapshot',
                'salary_accruals.paid_customer_amount_at_accrual',
                'salary_accruals.payable_amount_computed',
                'salary_accruals.paid_amount_fact',
                'salary_accruals.unpaid_amount',
                'salary_accruals.meta',
                'orders.order_number',
                'users.name as user_name',
            ])
            ->orderBy('users.name')
            ->orderBy('orders.order_number')
            ->get();

        return $rows->map(function (object $row) use ($period): array {
            $accrual = SalaryAccrual::query()->find($row->id);
            $payable = $accrual instanceof SalaryAccrual
                ? $this->payableForAccrualInPeriod($accrual, $period)
                : 0.0;
            $paidInPeriod = $accrual instanceof SalaryAccrual
                ? $this->allocatedAmountForAccrualInPeriod($accrual->id, $period->id)
                : 0.0;
            $meta = is_string($row->meta) ? json_decode($row->meta, true) : (array) $row->meta;

            return [
                'accrual_id' => (int) $row->id,
                'order_id' => (int) $row->order_id,
                'order_number' => $row->order_number,
                'user_id' => (int) $row->user_id,
                'user_name' => $row->user_name,
                'accrued_salary' => round((float) $row->salary_amount, 2),
                'payable_in_period' => round($payable, 2),
                'paid_in_period' => round($paidInPeriod, 2),
                'paid_total' => round((float) $row->paid_amount_fact, 2),
                'unpaid_total' => round((float) $row->unpaid_amount, 2),
                'customer_rate' => round((float) $row->customer_rate_snapshot, 2),
                'customer_paid_amount' => round((float) $row->paid_customer_amount_at_accrual, 2),
                'customer_fully_paid' => $this->isCustomerFullyPaid((float) $row->customer_rate_snapshot, (float) $row->paid_customer_amount_at_accrual),
                'payable_total' => round((float) $row->payable_amount_computed, 2),
                'calculation_mode' => $meta['calculation_mode'] ?? 'kpi',
            ];
        })->values()->all();
    }

    public function approvePeriod(SalaryPeriod $period, ?int $userId): void
    {
        $period->update([
            'status' => 'approved',
            'approved_by' => $userId,
        ]);
    }

    public function closePeriod(SalaryPeriod $period, ?int $userId): void
    {
        $period->update([
            'status' => 'closed',
            'closed_by' => $userId,
        ]);
    }

    /**
     * @return Collection<int, SalaryPeriod>
     */
    public function periods(): Collection
    {
        return SalaryPeriod::query()
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->get();
    }

    private function paidCustomerAmountForOrderUntil(int $orderId, string $date): float
    {
        if (! Schema::hasTable('payment_schedules')) {
            return 0.0;
        }

        $amount = DB::table('payment_schedules')
            ->where('order_id', $orderId)
            ->where('party', 'customer')
            ->where('status', 'paid')
            ->whereDate('actual_date', '<=', $date)
            ->sum('amount');

        return (float) $amount;
    }

    private function payableForAccrualInPeriod(SalaryAccrual $accrual, SalaryPeriod $period): float
    {
        $payableToEnd = $this->payableForAccrualToPeriodEnd($accrual, $period);
        $paidBeforePeriod = $this->allocatedAmountForAccrualUntil($accrual->id, $period->period_start->copy()->subDay()->toDateString());

        return round(max(0.0, $payableToEnd - $paidBeforePeriod), 2);
    }

    private function payableForAccrualToPeriodEnd(SalaryAccrual $accrual, SalaryPeriod $period): float
    {
        $salaryAmount = (float) $accrual->salary_amount;
        $customerRate = (float) $accrual->customer_rate_snapshot;

        if ($salaryAmount <= 0 || $customerRate <= 0) {
            return 0.0;
        }

        $paidToEnd = $this->paidCustomerAmountForOrderUntil((int) $accrual->order_id, $period->period_end->toDateString());

        return $this->isCustomerFullyPaid($customerRate, $paidToEnd) ? round($salaryAmount, 2) : 0.0;
    }

    private function availableToAllocateForAccrual(SalaryAccrual $accrual, SalaryPeriod $period): float
    {
        $payableToEnd = $this->payableForAccrualToPeriodEnd($accrual, $period);
        $allocatedTotal = $this->allocatedAmountForAccrualTotal($accrual->id);

        return round(max(0.0, $payableToEnd - $allocatedTotal), 2);
    }

    private function allocatedAmountForAccrualUntil(int $accrualId, string $date): float
    {
        $sum = DB::table('salary_payout_allocations')
            ->join('salary_payouts', 'salary_payouts.id', '=', 'salary_payout_allocations.payout_id')
            ->where('salary_payout_allocations.accrual_id', $accrualId)
            ->whereDate('salary_payouts.payout_date', '<=', $date)
            ->sum('salary_payout_allocations.amount');

        return (float) $sum;
    }

    private function allocatedAmountForAccrualInPeriod(int $accrualId, int $periodId): float
    {
        $sum = DB::table('salary_payout_allocations')
            ->join('salary_payouts', 'salary_payouts.id', '=', 'salary_payout_allocations.payout_id')
            ->where('salary_payout_allocations.accrual_id', $accrualId)
            ->where('salary_payouts.period_id', $periodId)
            ->sum('salary_payout_allocations.amount');

        return (float) $sum;
    }

    private function allocatedAmountForAccrualTotal(int $accrualId): float
    {
        return (float) DB::table('salary_payout_allocations')
            ->where('accrual_id', $accrualId)
            ->sum('amount');
    }

    private function settleOpenAdvancesForPeriod(SalaryPeriod $period): void
    {
        $userIds = SalaryPayout::query()
            ->where('type', 'advance')
            ->whereDate('payout_date', '<=', $period->period_end)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $this->settleOpenAdvancesForUser((int) $userId, $period);
        }
    }

    private function settleOpenAdvancesForUser(int $userId, SalaryPeriod $period): void
    {
        $advancePayouts = SalaryPayout::query()
            ->where('user_id', $userId)
            ->where('type', 'advance')
            ->whereDate('payout_date', '<=', $period->period_end)
            ->orderBy('payout_date')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $accruals = SalaryAccrual::query()
            ->where('user_id', $userId)
            ->orderBy('order_date_snapshot')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($advancePayouts as $payout) {
            $amountLeft = round((float) $payout->amount - (float) $payout->allocations()->sum('amount'), 2);

            if ($amountLeft <= 0) {
                continue;
            }

            foreach ($accruals as $accrual) {
                if ($amountLeft <= 0) {
                    break;
                }

                $available = $this->availableToAllocateForAccrual($accrual, $period);

                if ($available <= 0) {
                    continue;
                }

                $portion = min($amountLeft, $available);
                $payout->allocations()->create([
                    'accrual_id' => $accrual->id,
                    'amount' => $portion,
                ]);

                $this->refreshAccrualPaymentTotals($accrual);
                $amountLeft = round($amountLeft - $portion, 2);
            }
        }
    }

    private function unallocatedAdvanceAmountForUserUntil(int $userId, string $date): float
    {
        $payouts = SalaryPayout::query()
            ->where('user_id', $userId)
            ->where('type', 'advance')
            ->whereDate('payout_date', '<=', $date)
            ->withSum('allocations', 'amount')
            ->get();

        return (float) $payouts->sum(
            fn (SalaryPayout $payout): float => max(0.0, (float) $payout->amount - (float) ($payout->allocations_sum_amount ?? 0))
        );
    }

    private function refreshAccrualPaymentTotals(SalaryAccrual $accrual): void
    {
        $newPaidFact = $this->allocatedAmountForAccrualTotal($accrual->id);
        $accrual->update([
            'paid_amount_fact' => $newPaidFact,
            'unpaid_amount' => round(max(0.0, (float) $accrual->salary_amount - $newPaidFact), 2),
        ]);
    }

    private function fallbackSalaryAmount(float $delta, ?SalaryCoefficient $salaryCoefficient): float
    {
        if ($salaryCoefficient === null) {
            return round($delta / 2, 2);
        }

        $baseSalary = (float) ($salaryCoefficient->base_salary ?? 0);
        $bonusPercent = (float) ($salaryCoefficient->bonus_percent ?? 0);

        if ($baseSalary === 0.0 && $bonusPercent === 0.0) {
            return round($delta / 2, 2);
        }

        return round(($delta * ($bonusPercent / 100)) + $baseSalary, 2);
    }

    private function isCustomerFullyPaid(float $customerRate, float $paidAmount): bool
    {
        return $customerRate > 0 && $paidAmount + 0.009 >= $customerRate;
    }
}
