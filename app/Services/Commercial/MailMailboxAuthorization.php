<?php

namespace App\Services\Commercial;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

final class MailMailboxAuthorization
{
    public function canViewAllMailboxes(User $user): bool
    {
        return $user->isAdmin()
            || $user->isSupervisor()
            || RoleAccess::canAccessSettingsSystem($user);
    }

    public function canAccessThread(User $user, MailThread $thread): bool
    {
        if ($this->canViewAllMailboxes($user)) {
            return true;
        }

        if (! Schema::hasColumn('mail_threads', 'mailbox_user_id')) {
            return false;
        }

        $mailboxUserId = $thread->mailbox_user_id;

        return $mailboxUserId === null || (int) $mailboxUserId === (int) $user->id;
    }

    public function canAccessMessage(User $user, MailMessage $message): bool
    {
        $message->loadMissing('thread');

        if ($message->thread === null) {
            return false;
        }

        return $this->canAccessThread($user, $message->thread);
    }

    /**
     * @param  Builder<MailThread>  $query
     */
    public function applyThreadScope(Builder $query, User $user): void
    {
        if ($this->canViewAllMailboxes($user)) {
            return;
        }

        if (! Schema::hasColumn('mail_threads', 'mailbox_user_id')) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $scoped) use ($user): void {
            $scoped->where('mailbox_user_id', $user->id)
                ->orWhereNull('mailbox_user_id');
        });
    }
}
