<?php

namespace App\Services;

use App\Models\Contractor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContractorCreditService
{
    /**
     * @param  list<int>  $contractorIds
     * @return array<int, float>
     */
    public function currentDebtByContractorIds(array $contractorIds): array
    {
        $contractorIds = array_values(array_unique(array_filter($contractorIds, static fn (mixed $id): bool => is_numeric($id))));

        if ($contractorIds === []) {
            return [];
        }

        $query = DB::table('payment_schedules')
            ->join('orders', 'orders.id', '=', 'payment_schedules.order_id')
            ->whereIn('orders.customer_id', $contractorIds)
            ->where('payment_schedules.party', 'customer')
            ->whereIn('payment_schedules.status', ['pending', 'overdue'])
            ->groupBy('orders.customer_id')
            ->selectRaw('orders.customer_id as contractor_id, COALESCE(SUM(payment_schedules.amount), 0) as current_debt');

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('orders.deleted_at');
        }

        return $query
            ->pluck('current_debt', 'contractor_id')
            ->mapWithKeys(fn (mixed $amount, mixed $contractorId): array => [(int) $contractorId => round((float) $amount, 2)])
            ->all();
    }

    public function currentDebtForContractor(int $contractorId): float
    {
        return $this->currentDebtByContractorIds([$contractorId])[$contractorId] ?? 0.0;
    }

    public function isBlockedByDebtLimit(Contractor $contractor, ?float $currentDebt = null): bool
    {
        if ($contractor->isOwnCompanyProfile()) {
            return false;
        }

        if (! $this->supportsDebtLimit()) {
            return false;
        }

        if (! (bool) ($contractor->stop_on_limit ?? false)) {
            return false;
        }

        $debtLimit = $contractor->debt_limit;

        if ($debtLimit === null) {
            return false;
        }

        $currentDebt ??= $this->currentDebtForContractor($contractor->id);

        return round((float) $currentDebt, 2) >= round((float) $debtLimit, 2);
    }

    public function supportsDebtLimit(): bool
    {
        return true;
    }
}
