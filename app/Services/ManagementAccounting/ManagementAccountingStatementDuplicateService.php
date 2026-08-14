<?php

declare(strict_types=1);

namespace App\Services\ManagementAccounting;

use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Support\ManagementStatementLineContentFingerprint;
use Illuminate\Support\Collection;

final class ManagementAccountingStatementDuplicateService
{
    public function existsTwin(
        int $bankAccountId,
        string $operationDate,
        string $direction,
        float $amount,
        string $description,
        string $source,
        ?int $exceptLineId = null,
    ): bool {
        return $this->findTwin(
            $bankAccountId,
            $operationDate,
            $direction,
            $amount,
            $description,
            $source,
            $exceptLineId,
        ) !== null;
    }

    public function findTwin(
        int $bankAccountId,
        string $operationDate,
        string $direction,
        float $amount,
        string $description,
        string $source,
        ?int $exceptLineId = null,
    ): ?ManagementStatementLine {
        $candidates = $this->candidates($bankAccountId, $operationDate, $direction, $amount, $exceptLineId);

        foreach ($candidates as $candidate) {
            if ($this->isContentDuplicate($description, $source, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Удаляет pending-строки, которые повторяют уже существующую операцию (часто xls + OData).
     *
     * @return list<int>
     */
    public function deletePendingTwins(): array
    {
        $deletedIds = [];
        $importIds = [];
        $pending = ManagementStatementLine::query()
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();

        foreach ($pending as $line) {
            if (in_array($line->id, $deletedIds, true)) {
                continue;
            }

            $twin = $this->findTwin(
                (int) $line->bank_account_id,
                (string) $line->operation_date?->toDateString(),
                (string) $line->direction,
                (float) $line->amount,
                (string) $line->description,
                (string) $line->source,
                (int) $line->id,
            );

            if ($twin === null) {
                continue;
            }

            $keepAllocated = $twin->status === 'allocated';
            $keepOlderPending = $twin->status === 'pending' && $twin->id < $line->id;

            if (! $keepAllocated && ! $keepOlderPending) {
                continue;
            }

            $deletedIds[] = $line->id;
            if ($line->import_id !== null) {
                $importIds[] = (int) $line->import_id;
            }
            $line->delete();
        }

        $this->recountImports($importIds);

        return $deletedIds;
    }

    /**
     * @param  list<int>  $importIds
     */
    public function recountImports(array $importIds): void
    {
        foreach (array_unique(array_filter($importIds)) as $importId) {
            $import = ManagementStatementImport::query()->find((int) $importId);
            if ($import === null) {
                continue;
            }

            $count = ManagementStatementLine::query()->where('import_id', $importId)->count();
            $allocated = ManagementStatementLine::query()
                ->where('import_id', $importId)
                ->where('status', 'allocated')
                ->count();

            $import->update([
                'lines_count' => $count,
                'lines_allocated' => $allocated,
                'status' => $allocated >= $count && $count > 0 ? 'reconciled' : 'draft',
            ]);
        }
    }

    /**
     * @return Collection<int, ManagementStatementLine>
     */
    private function candidates(
        int $bankAccountId,
        string $operationDate,
        string $direction,
        float $amount,
        ?int $exceptLineId,
    ): Collection {
        return ManagementStatementLine::query()
            ->where('bank_account_id', $bankAccountId)
            ->whereDate('operation_date', $operationDate)
            ->where('direction', $direction)
            ->where('amount', number_format($amount, 2, '.', ''))
            ->when($exceptLineId !== null, fn ($query) => $query->where('id', '!=', $exceptLineId))
            ->orderBy('id')
            ->get();
    }

    private function isContentDuplicate(string $description, string $source, ManagementStatementLine $other): bool
    {
        $normalized = ManagementStatementLineContentFingerprint::normalizeDescription($description);
        $otherNormalized = ManagementStatementLineContentFingerprint::normalizeDescription((string) $other->description);

        if ($normalized === '' || $normalized !== $otherNormalized) {
            return false;
        }

        if (ManagementStatementLineContentFingerprint::hasDistinguishingToken($normalized)) {
            return true;
        }

        return $source !== (string) $other->source;
    }
}
