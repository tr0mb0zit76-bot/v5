<?php

namespace App\Support;

use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class UserDepartmentSync
{
    /**
     * @param  list<int|string|null>  $approvalDepartmentIds
     */
    public static function sync(User $user, ?int $primaryDepartmentId, array $approvalDepartmentIds): void
    {
        if (! Schema::hasTable('department_user')) {
            return;
        }

        $approvalIds = collect($approvalDepartmentIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($primaryDepartmentId !== null && $primaryDepartmentId > 0) {
            $approvalIds = $approvalIds->push($primaryDepartmentId)->unique()->values();
        }

        if ($approvalIds->isEmpty()) {
            $user->departments()->detach();

            return;
        }

        $validIds = Department::query()
            ->whereIn('id', $approvalIds->all())
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        if ($validIds === []) {
            $user->departments()->detach();

            return;
        }

        $primaryId = $primaryDepartmentId !== null && $primaryDepartmentId > 0 && in_array($primaryDepartmentId, $validIds, true)
            ? $primaryDepartmentId
            : null;

        if ($primaryId === null && count($validIds) === 1) {
            $primaryId = $validIds[0];
        }

        $approvalSet = collect($approvalDepartmentIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->flip();

        $syncPayload = [];

        foreach ($validIds as $departmentId) {
            $syncPayload[$departmentId] = [
                'is_primary' => $primaryId !== null && $departmentId === $primaryId,
                'receives_approvals' => $approvalSet->has($departmentId),
            ];
        }

        $user->departments()->sync($syncPayload);
    }
}
