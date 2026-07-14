<?php

namespace App\Services;

use App\Enums\ConversationParticipantRole;
use App\Enums\ConversationPostingPolicy;
use App\Models\Conversation;
use App\Models\User;

class MessengerGroupAccessService
{
    public function roleFor(Conversation $conversation, User $user): ?ConversationParticipantRole
    {
        if (
            $conversation->type === 'group'
            && (int) $conversation->created_by === (int) $user->id
        ) {
            return ConversationParticipantRole::Owner;
        }

        $role = $conversation->participants()
            ->where('users.id', $user->id)
            ->value('conversation_participants.role');

        return ConversationParticipantRole::tryFrom((string) $role)
            ?? ($role === null ? null : ConversationParticipantRole::Member);
    }

    public function canManage(Conversation $conversation, User $user): bool
    {
        return $conversation->type === 'group'
            && $this->roleFor($conversation, $user) === ConversationParticipantRole::Owner;
    }

    public function canPost(Conversation $conversation, User $user): bool
    {
        if ($conversation->type !== 'group') {
            return true;
        }

        $role = $this->roleFor($conversation, $user);
        if ($role === null) {
            return false;
        }

        $policy = $conversation->posting_policy instanceof ConversationPostingPolicy
            ? $conversation->posting_policy
            : ConversationPostingPolicy::tryFrom((string) $conversation->posting_policy);

        return match ($policy ?? ConversationPostingPolicy::Members) {
            ConversationPostingPolicy::Members => true,
            ConversationPostingPolicy::Admins => in_array(
                $role,
                [ConversationParticipantRole::Owner, ConversationParticipantRole::Admin],
                true,
            ),
            ConversationPostingPolicy::Owner => $role === ConversationParticipantRole::Owner,
        };
    }
}
