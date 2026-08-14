<?php

declare(strict_types=1);

namespace App\Services\ManagementAccounting;

use App\Models\ManagementStatementLine;
use App\Models\User;

final class ManagementAccountingStatementRewalkService
{
    public function __construct(
        private readonly ManagementAccountingStatementDuplicateService $duplicates,
        private readonly ManagementAccountingAllocationService $allocation,
        private readonly ManagementAccountingMatchingService $matching,
        private readonly ManagementAccountingAutoAllocateService $autoAllocate,
    ) {}

    /**
     * @return array{
     *     duplicates_deleted: int,
     *     allocated_twins_deleted: int,
     *     ambiguous_released: int,
     *     rematched: int,
     *     allocated: int
     * }
     */
    public function rewalk(User $actor, int $minConfidence = 55): array
    {
        $deleted = $this->duplicates->deletePendingTwins();
        $allocatedTwins = $this->releaseAllocatedTwins($actor);
        $released = $this->releaseAmbiguousCategoryDumps($actor);
        $this->matching->flushScheduleCache();
        $rematched = $this->rematchPending();
        $allocated = $this->autoAllocatePending($actor, $minConfidence);

        return [
            'duplicates_deleted' => count($deleted),
            'allocated_twins_deleted' => $allocatedTwins,
            'ambiguous_released' => $released,
            'rematched' => $rematched,
            'allocated' => $allocated,
        ];
    }

    private function releaseAllocatedTwins(User $actor): int
    {
        $deleted = 0;
        $importIds = [];
        $allocated = ManagementStatementLine::query()
            ->where('status', 'allocated')
            ->orderByDesc('id')
            ->get();

        foreach ($allocated as $line) {
            $twin = $this->duplicates->findTwin(
                (int) $line->bank_account_id,
                (string) $line->operation_date?->toDateString(),
                (string) $line->direction,
                (float) $line->amount,
                (string) $line->description,
                (string) $line->source,
                (int) $line->id,
            );

            if ($twin === null || $twin->status !== 'allocated' || $twin->id >= $line->id) {
                continue;
            }

            if ($line->import_id !== null) {
                $importIds[] = (int) $line->import_id;
            }

            $this->allocation->deallocateLine($line, $actor, 'Дубль выписки (xls/OData)');
            $line->delete();
            $deleted++;
        }

        $this->duplicates->recountImports($importIds);

        return $deleted;
    }

    private function releaseAmbiguousCategoryDumps(User $actor): int
    {
        $lines = ManagementStatementLine::query()
            ->where('status', 'allocated')
            ->whereNull('allocation_payment_schedule_id')
            ->where(function ($query): void {
                $query->where('match_notes', 'like', '%Несколько заявок%')
                    ->orWhere('match_notes', 'like', '%выберите строку графика%');
            })
            ->orderBy('id')
            ->get();

        $released = 0;

        foreach ($lines as $line) {
            $this->allocation->deallocateLine($line, $actor, 'Снятие ошибочного разноса на статью (несколько заявок)');
            $released++;
        }

        return $released;
    }

    private function rematchPending(): int
    {
        $lines = ManagementStatementLine::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        $count = 0;

        foreach ($lines as $line) {
            $suggestion = $this->matching->suggestForLine($line);
            unset($suggestion['suggested_candidates']);
            $line->fill($suggestion)->save();
            $count++;
        }

        return $count;
    }

    private function autoAllocatePending(User $actor, int $minConfidence): int
    {
        $lines = ManagementStatementLine::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->get();

        $allocated = 0;

        foreach ($lines as $line) {
            $line->refresh();
            if ($this->autoAllocate->tryAutoAllocate($line, $actor, $minConfidence)) {
                $allocated++;
            }
        }

        return $allocated;
    }
}
