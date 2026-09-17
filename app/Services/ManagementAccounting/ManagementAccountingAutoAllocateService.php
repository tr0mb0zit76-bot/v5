<?php

declare(strict_types=1);

namespace App\Services\ManagementAccounting;

use App\Models\ManagementStatementLine;
use App\Models\User;

/**
 * Авторазнос только однозначных подсказок матчера.
 * Неоднозначный operational (несколько заявок) не сбрасываем на статью.
 */
final class ManagementAccountingAutoAllocateService
{
    public function __construct(
        private readonly ManagementAccountingAllocationService $allocationService,
        private readonly ManagementAccountingMatchingService $matching,
    ) {}

    public function tryAutoAllocate(ManagementStatementLine $line, User $actor, int $minConfidence): bool
    {
        if ($line->status === 'allocated') {
            return false;
        }

        $confidence = (int) ($line->match_confidence ?? 0);
        if ($confidence < $minConfidence) {
            return false;
        }

        $notes = (string) ($line->match_notes ?? '');
        $ambiguousManual = str_contains($notes, 'Несколько заявок')
            || str_contains($notes, 'выберите строку графика')
            || str_contains($notes, 'выберите split вручную');

        $suggestion = $this->matching->suggestForLine($line);
        $allocations = $suggestion['suggested_allocations'] ?? null;
        $suggestionConfidence = (int) ($suggestion['match_confidence'] ?? 0);
        $suggestionNotes = (string) ($suggestion['match_notes'] ?? '');

        if (
            is_array($allocations)
            && count($allocations) >= 2
            && $suggestionConfidence >= $minConfidence
            && ! str_contains($suggestionNotes, 'выберите split вручную')
        ) {
            $this->allocationService->allocateLine($line, [
                'allocation_type' => 'operational',
                'allocations' => $allocations,
            ], $actor);
            $this->matching->flushScheduleCache();

            return true;
        }

        if ($ambiguousManual) {
            return false;
        }

        if ($line->suggested_payment_schedule_id) {
            $this->allocationService->allocateLine($line, [
                'allocation_type' => 'operational',
                'payment_schedule_id' => (int) $line->suggested_payment_schedule_id,
            ], $actor);
            $this->matching->flushScheduleCache();

            return true;
        }

        if ($line->suggested_user_id && ($line->match_type === 'payroll' || str_contains((string) $line->match_type, 'payroll'))) {
            $this->allocationService->allocateLine($line, [
                'allocation_type' => 'payroll',
                'user_id' => (int) $line->suggested_user_id,
                'category_id' => $line->suggested_category_id,
            ], $actor);

            return true;
        }

        if ($line->suggested_category_id && $line->match_type === 'category') {
            $this->allocationService->allocateLine($line, [
                'allocation_type' => 'category',
                'category_id' => (int) $line->suggested_category_id,
            ], $actor);

            return true;
        }

        return false;
    }
}
