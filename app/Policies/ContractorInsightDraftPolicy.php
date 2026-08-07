<?php

namespace App\Policies;

use App\Models\Contractor;
use App\Models\ContractorInsightDraft;
use App\Models\User;
use App\Support\ContractorPortraitAuthorization;

class ContractorInsightDraftPolicy
{
    public function review(User $user, ContractorInsightDraft $draft): bool
    {
        $contractor = Contractor::query()->find($draft->contractor_id);

        if ($contractor === null) {
            return false;
        }

        return ContractorPortraitAuthorization::canManage($user, $contractor);
    }
}
