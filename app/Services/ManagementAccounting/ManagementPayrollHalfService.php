<?php

namespace App\Services\ManagementAccounting;

use App\Models\ManagementPayrollHalf;
use App\Models\ManagementPayrollHalfUser;
use App\Models\SalaryAccrual;
use App\Support\ManagementPayrollHalfCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ManagementPayrollHalfService
{
    /**
     * @return array<string, mixed>
     */
    public function ensureCurrentHalf(?CarbonImmutable $date = null): array
    {
        $date ??= CarbonImmutable::now();
        $definition = ManagementPayrollHalfCalendar::resolveForDate($date);

        $half = ManagementPayrollHalf::query()->firstOrCreate(
            [
                'year' => $definition['year'],
                'month' => $definition['month'],
                'half' => $definition['half'],
            ],
            [
                'period_start' => $definition['period_start'],
                'period_end' => $definition['period_end'],
                'payment_date' => $definition['payment_date'],
                'status' => 'open',
            ],
        );

        $this->syncAccruals($half);

        return $this->serializeHalf($half->fresh(['users.user']));
    }

    public function syncAccruals(ManagementPayrollHalf $half): void
    {
        if (! Schema::hasTable('salary_accruals') || ! Schema::hasTable('salary_periods')) {
            return;
        }

        $accruals = SalaryAccrual::query()
            ->select([
                'user_id',
                DB::raw('SUM(payable_amount_computed) as accrued_total'),
            ])
            ->whereHas('period', function ($query) use ($half): void {
                $query->whereDate('period_start', '<=', $half->period_end)
                    ->whereDate('period_end', '>=', $half->period_start);
            })
            ->groupBy('user_id')
            ->get();

        foreach ($accruals as $row) {
            ManagementPayrollHalfUser::query()->updateOrCreate(
                [
                    'payroll_half_id' => $half->id,
                    'user_id' => (int) $row->user_id,
                ],
                [
                    'accrued_amount' => round((float) $row->accrued_total, 2),
                ],
            );
        }
    }

    public function addPaidAmount(ManagementPayrollHalf $half, int $userId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $row = ManagementPayrollHalfUser::query()->firstOrCreate(
            [
                'payroll_half_id' => $half->id,
                'user_id' => $userId,
            ],
            [
                'accrued_amount' => 0,
                'paid_amount' => 0,
            ],
        );

        $row->paid_amount = round((float) $row->paid_amount + $amount, 2);
        $row->save();
    }

    public function subtractPaidAmount(ManagementPayrollHalf $half, int $userId, float $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        $row = ManagementPayrollHalfUser::query()
            ->where('payroll_half_id', $half->id)
            ->where('user_id', $userId)
            ->first();

        if ($row === null) {
            return;
        }

        $row->paid_amount = max(0, round((float) $row->paid_amount - $amount, 2));
        $row->save();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentHalves(int $limit = 6): array
    {
        return ManagementPayrollHalf::query()
            ->with(['users.user:id,name'])
            ->orderByDesc('payment_date')
            ->limit($limit)
            ->get()
            ->map(fn (ManagementPayrollHalf $half): array => $this->serializeHalf($half))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeHalf(ManagementPayrollHalf $half): array
    {
        $half->loadMissing(['users.user:id,name']);

        $users = $half->users->map(static fn (ManagementPayrollHalfUser $row): array => [
            'user_id' => $row->user_id,
            'user_name' => $row->user?->name,
            'accrued_amount' => (float) $row->accrued_amount,
            'paid_amount' => (float) $row->paid_amount,
        ])->values()->all();

        return [
            'id' => $half->id,
            'year' => $half->year,
            'month' => $half->month,
            'half' => $half->half,
            'period_start' => $half->period_start?->toDateString(),
            'period_end' => $half->period_end?->toDateString(),
            'payment_date' => $half->payment_date?->toDateString(),
            'status' => $half->status,
            'accrued_total' => round($half->users->sum(static fn (ManagementPayrollHalfUser $row): float => (float) $row->accrued_amount), 2),
            'paid_total' => round($half->users->sum(static fn (ManagementPayrollHalfUser $row): float => (float) $row->paid_amount), 2),
            'users' => $users,
        ];
    }
}
