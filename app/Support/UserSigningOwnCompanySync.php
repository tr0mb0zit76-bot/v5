<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

final class UserSigningOwnCompanySync
{
    /**
     * @param  list<int|string|null>  $rawIds
     */
    public static function sync(User $user, bool $hasSigningAuthority, array $rawIds): void
    {
        if (! Schema::hasTable('user_signing_own_company')) {
            return;
        }

        if (! $hasSigningAuthority) {
            $user->signingOwnCompanies()->detach();

            return;
        }

        $ids = collect($rawIds)
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        if ($ids === []) {
            $user->signingOwnCompanies()->detach();

            return;
        }

        $validIds = Contractor::query()
            ->whereIn('id', $ids)
            ->where('is_own_company', true)
            ->pluck('id')
            ->all();

        $user->signingOwnCompanies()->sync($validIds);
    }
}
