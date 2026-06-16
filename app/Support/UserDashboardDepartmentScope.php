<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class UserDashboardDepartmentScope
{
    /**
     * ID пользователей подразделений руководителя (основное + согласования).
     *
     * @return list<int>
     */
    public static function departmentUserIds(User $user): array
    {
        if (! Schema::hasTable('department_user')) {
            return [(int) $user->id];
        }

        $departmentIds = array_values(array_unique(array_filter([
            $user->primaryDepartmentId(),
            ...$user->approvalDepartmentIds(),
        ], static fn (?int $id): bool => $id !== null && $id > 0)));

        if ($departmentIds === []) {
            return [(int) $user->id];
        }

        return DB::table('department_user')
            ->whereIn('department_id', $departmentIds)
            ->pluck('user_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
