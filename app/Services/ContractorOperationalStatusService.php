<?php

namespace App\Services;

use App\Models\Contractor;
use App\Models\Order;
use App\Support\ContractorWorkStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContractorOperationalStatusService
{
    public const VERIFICATION_TTL_MONTHS = 3;

    public const INACTIVITY_PAUSE_MONTHS = 3;

    /**
     * @param  EloquentCollection<int, Contractor>|Collection<int, Contractor>  $contractors
     */
    public function syncMany(EloquentCollection|Collection $contractors): void
    {
        $this->applyOperationalRulesToMany($contractors, persist: true);
    }

    /**
     * Вычисляет операционный статус в памяти для отображения в гриде без записи в БД.
     * Персистентная синхронизация — при открытии карточки (`sync`) или по расписанию.
     *
     * @param  EloquentCollection<int, Contractor>|Collection<int, Contractor>  $contractors
     */
    public function enrichManyForDisplay(EloquentCollection|Collection $contractors): void
    {
        $this->applyOperationalRulesToMany($contractors, persist: false);
    }

    public function sync(Contractor $contractor): Contractor
    {
        if ($contractor->isOwnCompanyProfile()) {
            return $contractor;
        }

        $lastOrderDate = $this->lastOrderDateForContractor($contractor->id);
        $this->applyOperationalRules($contractor, $lastOrderDate);

        if ($contractor->isDirty()) {
            $contractor->save();
        }

        return $contractor->refresh();
    }

    /**
     * @param  array<string, mixed>  $scoringPayload
     */
    public function markVerifiedFromScoring(Contractor $contractor, array $scoringPayload): void
    {
        if ($contractor->isOwnCompanyProfile()) {
            return;
        }

        $metadata = is_array($contractor->metadata) ? $contractor->metadata : [];
        $metadata['checko_scoring'] = [
            'score' => $scoringPayload['score'] ?? null,
            'grade' => $scoringPayload['grade'] ?? null,
            'tier' => $scoringPayload['tier'] ?? null,
            'model_version' => config('contractor_scoring.model_version'),
            'updated_at' => now()->toIso8601String(),
        ];

        $contractor->forceFill([
            'is_verified' => true,
            'verified_at' => now(),
            'metadata' => $metadata,
        ])->save();
    }

    public function isVerificationExpired(?CarbonInterface $verifiedAt): bool
    {
        if ($verifiedAt === null) {
            return true;
        }

        return $verifiedAt->lt(now()->subMonths(self::VERIFICATION_TTL_MONTHS));
    }

    public function verificationValidUntil(?CarbonInterface $verifiedAt): ?CarbonInterface
    {
        if ($verifiedAt === null) {
            return null;
        }

        return $verifiedAt->copy()->addMonths(self::VERIFICATION_TTL_MONTHS);
    }

    public function resolveStatusText(Contractor $contractor): string
    {
        if ($contractor->isOwnCompanyProfile()) {
            return 'Своя компания';
        }

        if (! $contractor->is_active) {
            return 'Архив';
        }

        return ContractorWorkStatus::label($contractor->work_status);
    }

    /**
     * @return array{badge: string, text: string}
     */
    public function resolveStatusBadge(Contractor $contractor): array
    {
        if ($contractor->isOwnCompanyProfile()) {
            return [
                'badge' => 'bg-sky-100 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
                'text' => 'Своя компания',
            ];
        }

        return ContractorWorkStatus::badgeClasses(
            $contractor->work_status,
            ! $contractor->is_active,
        );
    }

    /**
     * @param  EloquentCollection<int, Contractor>|Collection<int, Contractor>  $contractors
     */
    private function applyOperationalRulesToMany(EloquentCollection|Collection $contractors, bool $persist): void
    {
        if ($contractors->isEmpty()) {
            return;
        }

        $operationalContractors = $contractors->filter(
            fn (mixed $contractor): bool => $contractor instanceof Contractor && ! $contractor->isOwnCompanyProfile(),
        );

        if ($operationalContractors->isEmpty()) {
            return;
        }

        $ids = $operationalContractors->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $lastOrderDates = $this->lastOrderDatesForContractorIds($ids);

        foreach ($operationalContractors as $contractor) {
            if (! $contractor instanceof Contractor) {
                continue;
            }

            $this->applyOperationalRules(
                $contractor,
                $lastOrderDates[$contractor->id] ?? null,
            );

            if ($persist && $contractor->isDirty()) {
                $contractor->save();
            }
        }
    }

    private function applyOperationalRules(Contractor $contractor, ?CarbonInterface $lastOrderDate): void
    {
        if ($contractor->isOwnCompanyProfile()) {
            return;
        }

        $this->syncVerificationExpiry($contractor);
        $this->syncWorkPauseFromInactivity($contractor, $lastOrderDate);
    }

    private function syncVerificationExpiry(Contractor $contractor): void
    {
        if (! $contractor->is_verified) {
            return;
        }

        if ($this->isVerificationExpired($contractor->verified_at)) {
            $contractor->is_verified = false;
        }
    }

    private function syncWorkPauseFromInactivity(Contractor $contractor, ?CarbonInterface $lastOrderDate): void
    {
        if (! $contractor->is_active) {
            return;
        }

        $inactiveThreshold = now()->subMonths(self::INACTIVITY_PAUSE_MONTHS)->startOfDay();
        $hasRecentOrder = $lastOrderDate !== null && $lastOrderDate->gte($inactiveThreshold);

        if ($hasRecentOrder) {
            if (
                $contractor->work_status === ContractorWorkStatus::WORK_PAUSE
                && $contractor->work_pause_is_automatic
            ) {
                $contractor->work_status = ContractorWorkStatus::ACTIVE;
                $contractor->work_pause_is_automatic = false;
            }

            return;
        }

        if ($contractor->work_status === ContractorWorkStatus::WORK_BAN) {
            return;
        }

        $contractor->work_status = ContractorWorkStatus::WORK_PAUSE;
        $contractor->work_pause_is_automatic = true;
    }

    private function lastOrderDateForContractor(int $contractorId): ?CarbonInterface
    {
        $map = $this->lastOrderDatesForContractorIds([$contractorId]);

        return $map[$contractorId] ?? null;
    }

    /**
     * @param  list<int>  $contractorIds
     * @return array<int, CarbonInterface>
     */
    private function lastOrderDatesForContractorIds(array $contractorIds): array
    {
        $contractorIds = array_values(array_unique(array_filter(
            array_map(fn (mixed $id): int => (int) $id, $contractorIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($contractorIds === [] || ! Schema::hasTable('orders')) {
            return [];
        }

        $dates = [];

        $customerDates = Order::query()
            ->whereIn('customer_id', $contractorIds)
            ->whereNotNull('order_date')
            ->selectRaw('customer_id as contractor_id, MAX(order_date) as last_order_date')
            ->groupBy('customer_id')
            ->pluck('last_order_date', 'contractor_id');

        foreach ($customerDates as $contractorId => $date) {
            $dates[(int) $contractorId] = $this->parseDate($date);
        }

        $carrierDates = Order::query()
            ->whereIn('carrier_id', $contractorIds)
            ->whereNotNull('order_date')
            ->selectRaw('carrier_id as contractor_id, MAX(order_date) as last_order_date')
            ->groupBy('carrier_id')
            ->pluck('last_order_date', 'contractor_id');

        foreach ($carrierDates as $contractorId => $date) {
            $this->mergeLastDate($dates, (int) $contractorId, $this->parseDate($date));
        }

        if (Schema::hasTable('leg_contractor_assignments') && Schema::hasTable('order_legs')) {
            $assignmentDates = DB::table('leg_contractor_assignments')
                ->join('order_legs', 'order_legs.id', '=', 'leg_contractor_assignments.order_leg_id')
                ->join('orders', 'orders.id', '=', 'order_legs.order_id')
                ->whereIn('leg_contractor_assignments.contractor_id', $contractorIds)
                ->whereNotNull('orders.order_date')
                ->selectRaw('leg_contractor_assignments.contractor_id as contractor_id, MAX(orders.order_date) as last_order_date')
                ->groupBy('leg_contractor_assignments.contractor_id')
                ->pluck('last_order_date', 'contractor_id');

            foreach ($assignmentDates as $contractorId => $date) {
                $this->mergeLastDate($dates, (int) $contractorId, $this->parseDate($date));
            }
        }

        return array_filter($dates);
    }

    /**
     * @param  array<int, CarbonInterface|null>  $dates
     */
    private function mergeLastDate(array &$dates, int $contractorId, ?CarbonInterface $candidate): void
    {
        if ($candidate === null) {
            return;
        }

        $existing = $dates[$contractorId] ?? null;

        if ($existing === null || $candidate->gt($existing)) {
            $dates[$contractorId] = $candidate;
        }
    }

    private function parseDate(mixed $value): ?CarbonInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof CarbonInterface) {
            return $value;
        }

        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
