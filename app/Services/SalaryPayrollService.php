<?php

namespace App\Services;

use App\Models\SalaryAccrual;
use App\Models\SalaryPayout;
use App\Models\SalaryPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            if (SalaryPayout::query()->where('period_id', $period->id)->exists()) {
                throw new \RuntimeException('Нельзя пересчитать период с уже проведенными выплатами.');
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
                    'orders.customer_rate',
                ])
                ->get();

            foreach ($orders as $order) {
                $delta = (float) ($order->delta ?? 0);
                $salaryAmount = round($delta / 2, 2);
                $customerRate = (float) ($order->customer_rate ?? 0);
                $paidAtAccrual = $this->paidCustomerAmountForOrderUntil($order->id, $period->period_end);
                $unlockedAtAccrual = $customerRate > 0
                    ? round(min($salaryAmount, $salaryAmount * min(1.0, $paidAtAccrual / $customerRate)), 2)
                    : 0.0;

                SalaryAccrual::query()->create([
                    'period_id' => $period->id,
                    'user_id' => (int) $order->manager_id,
                    'order_id' => (int) $order->id,
                    'order_date_snapshot' => $order->order_date,
                    'delta_snapshot' => $delta,
                    'salary_amount' => $salaryAmount,
                    'customer_rate_snapshot' => $customerRate,
                    'paid_customer_amount_at_accrual' => $paidAtAccrual,
                    'payable_amount_computed' => $unlockedAtAccrual,
                    'paid_amount_fact' => 0,
                    'unpaid_amount' => $salaryAmount,
                    'meta' => [
                        'unlocked_at_accrual' => $unlockedAtAccrual,
                    ],
                ]);
            }
        });
    }

    public function createPayout(SalaryPeriod $period, array $payload, ?int $createdBy): SalaryPayout
    {
        return DB::transaction(function () use ($period, $payload, $createdBy): SalaryPayout {
            $payout = SalaryPayout::query()->create([
                'period_id' => $period->id,
                'user_id' => (int) $payload['user_id'],
                'amount' => (float) $payload['amount'],
                'payout_date' => $payload['payout_date'],
                'type' => $payload['type'] ?? 'salary',
                'comment' => $payload['comment'] ?? null,
                'created_by' => $createdBy,
            ]);

            $amountLeft = (float) $payload['amount'];

            $accruals = SalaryAccrual::query()
                ->where('user_id', (int) $payload['user_id'])
                ->orderBy('order_date_snapshot')
                ->orderBy('id')
                ->get();

            /** @var SalaryAccrual $accrual */
            foreach ($accruals as $accrual) {
                if ($amountLeft <= 0) {
                    break;
                }

                $payableForPeriod = $this->payableForAccrualInPeriod($accrual, $period);
                $paidInCurrentPeriod = $this->allocatedAmountForAccrualInPeriod($accrual->id, $period->id);
                $available = max(0.0, $payableForPeriod - $paidInCurrentPeriod);
                if ($available <= 0) {
                    continue;
                }

                $portion = min($amountLeft, $available);

                $payout->allocations()->create([
                    'accrual_id' => $accrual->id,
                    'amount' => $portion,
                ]);

                $newPaidFact = $this->allocatedAmountForAccrualTotal($accrual->id);
                $accrual->update([
                    'paid_amount_fact' => $newPaidFact,
                    'unpaid_amount' => round(max(0.0, (float) $accrual->salary_amount - $newPaidFact), 2),
                ]);

                $amountLeft = round($amountLeft - $portion, 2);
            }

            if ($amountLeft > 0.009) {
                throw new \RuntimeException('Сумма выплаты превышает доступную сумму к выплате.');
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

            $accruedTotal = (float) SalaryAccrual::query()
                ->where('period_id', $period->id)
                ->where('user_id', $userId)
                ->sum('salary_amount');

            $accruals = SalaryAccrual::query()
                ->where('user_id', $userId)
                ->orderBy('order_date_snapshot')
                ->get();

            $payableTotal = $accruals->sum(fn (SalaryAccrual $accrual): float => $this->payableForAccrualInPeriod($accrual, $period));

            $paidTotal = (float) SalaryPayout::query()
                ->where('period_id', $period->id)
                ->where('user_id', $userId)
                ->sum('amount');

            return [
                'user_id' => $userId,
                'user_name' => $row->user_name,
                'accrued_total' => round($accruedTotal, 2),
                'payable_total' => round($payableTotal, 2),
                'paid_total' => round($paidTotal, 2),
                'unpaid_total' => round(max(0.0, $accruals->sum(fn (SalaryAccrual $accrual): float => (float) $accrual->unpaid_amount)), 2),
                'payable_left' => round(max(0.0, $payableTotal - $paidTotal), 2),
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
                'salary_accruals.paid_amount_fact',
                'salary_accruals.unpaid_amount',
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
        $salaryAmount = (float) $accrual->salary_amount;
        $customerRate = (float) $accrual->customer_rate_snapshot;
        if ($salaryAmount <= 0 || $customerRate <= 0) {
            return 0.0;
        }

        $paidToEnd = $this->paidCustomerAmountForOrderUntil((int) $accrual->order_id, $period->period_end->toDateString());
        $unlockedToEnd = min($salaryAmount, $salaryAmount * min(1.0, $paidToEnd / $customerRate));
        $paidBeforePeriod = $this->allocatedAmountForAccrualUntil($accrual->id, $period->period_start->copy()->subDay()->toDateString());

        return round(max(0.0, min($salaryAmount - $paidBeforePeriod, $unlockedToEnd - $paidBeforePeriod)), 2);
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
}
