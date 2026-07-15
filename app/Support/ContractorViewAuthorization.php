<?php

namespace App\Support;

use App\Models\Contractor;
use App\Models\User;

final class ContractorViewAuthorization
{
    public static function userCanViewContractor(
        ?User $user,
        Contractor $contractor,
        ?string $type = null,
    ): bool {
        if ($user === null) {
            return false;
        }

        return Contractor::query()
            ->visibleTo($user, $type)
            ->whereKey($contractor->getKey())
            ->exists();
    }

    public static function authorize(?User $user, Contractor $contractor, ?string $type = null): void
    {
        abort_unless(self::userCanViewContractor($user, $contractor, $type), 403);
    }
}
