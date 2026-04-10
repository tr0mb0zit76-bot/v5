<?php

namespace App\Policies;

use App\Models\SalesScriptPlaySession;
use App\Models\User;

class SalesScriptPlaySessionPolicy
{
    public function interact(User $user, SalesScriptPlaySession $session): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return (int) $session->user_id === (int) $user->id;
    }
}
