<?php

namespace App\Services\Mcp;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MailMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
    ) {}

    /**
     * @return array{threads: list<array<string, mixed>>, total: int}
     */
    public function searchThreads(User $user, string $query, int $limit = 15): array
    {
        $this->access->requireMailArea($user);

        if (! Schema::hasTable('mail_threads') || ! Schema::hasTable('mail_messages')) {
            return ['threads' => [], 'total' => 0];
        }

        $needle = trim($query);
        $limit = max(1, min($limit, 25));

        $builder = MailThread::query()
            ->with(['messages' => fn ($q) => $q->orderByDesc('sent_at')->limit(1)])
            ->orderByDesc('last_message_at');

        $this->applyMailboxScope($builder, $user);

        if ($needle !== '') {
            $builder->where(function (Builder $scoped) use ($needle): void {
                $scoped->where('subject', 'like', '%'.$needle.'%');

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $scoped->orWhere('id', (int) $needle);
                }

                $scoped->orWhereHas('messages', function (Builder $messages) use ($needle): void {
                    $messages->where('body_text', 'like', '%'.$needle.'%')
                        ->orWhere('from_email', 'like', '%'.$needle.'%')
                        ->orWhere('subject', 'like', '%'.$needle.'%');
                });
            });
        }

        $threads = $builder->limit($limit)->get();

        return [
            'threads' => $threads->map(fn (MailThread $thread): array => $this->summarizeThread($thread))->all(),
            'total' => $threads->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getThread(User $user, int $threadId, int $messageLimit = 20): array
    {
        $this->access->requireMailArea($user);

        if (! Schema::hasTable('mail_threads')) {
            throw new ModelNotFoundException('Почта недоступна.');
        }

        $messageLimit = max(1, min($messageLimit, 50));

        $builder = MailThread::query()->whereKey($threadId);
        $this->applyMailboxScope($builder, $user);

        /** @var MailThread|null $thread */
        $thread = $builder->first();

        if ($thread === null) {
            throw new ModelNotFoundException('Цепочка писем не найдена.');
        }

        $messages = MailMessage::query()
            ->where('mail_thread_id', $thread->id)
            ->orderByDesc('sent_at')
            ->limit($messageLimit)
            ->get()
            ->map(fn (MailMessage $message): array => $this->serializeMessage($message))
            ->all();

        return [
            'thread' => $this->summarizeThread($thread, includeRelations: true),
            'messages' => $messages,
        ];
    }

    /**
     * @return array{mailbox: array<string, mixed>, team: list<array<string, mixed>>}
     */
    public function syncStatus(User $user): array
    {
        $this->access->requireMailArea($user);

        $mailbox = [
            'user_id' => $user->id,
            'email' => $user->email,
            'mail_sync_enabled' => (bool) ($user->mail_sync_enabled ?? true),
            'has_imap_credential' => $user->hasMailImapCredential(),
            'mail_last_sync_at' => optional($user->mail_last_sync_at)?->toIso8601String(),
            'mail_last_sync_error' => $user->mail_last_sync_error,
            'imap_host' => (string) config('mail_sync.imap.host'),
        ];

        $team = [];

        if ($this->canViewTeamMailSyncStatus($user) && Schema::hasTable('users')) {
            $team = User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'mail_sync_enabled', 'mail_last_sync_at', 'mail_last_sync_error'])
                ->map(fn (User $member): array => [
                    'user_id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'mail_sync_enabled' => (bool) ($member->mail_sync_enabled ?? true),
                    'mail_last_sync_at' => optional($member->mail_last_sync_at)?->toIso8601String(),
                    'mail_last_sync_error' => $member->mail_last_sync_error,
                ])
                ->all();
        }

        return [
            'mailbox' => $mailbox,
            'team' => $team,
        ];
    }

    /**
     * @param  Builder<MailThread>  $query
     */
    private function applyMailboxScope(Builder $query, User $user): void
    {
        if ($this->canViewAllMailboxes($user)) {
            return;
        }

        if (! Schema::hasColumn('mail_threads', 'mailbox_user_id')) {
            throw new AuthenticationException('Нет доступа к чужим почтовым ящикам.');
        }

        $query->where(function (Builder $scoped) use ($user): void {
            $scoped->where('mailbox_user_id', $user->id)
                ->orWhereNull('mailbox_user_id');
        });
    }

    private function canViewAllMailboxes(User $user): bool
    {
        return $user->isAdmin() || RoleAccess::canAccessSettingsSystem($user);
    }

    private function canViewTeamMailSyncStatus(User $user): bool
    {
        return $user->isAdmin() || RoleAccess::canAccessSettingsSystem($user);
    }

    /**
     * @return array<string, mixed>
     */
    private function summarizeThread(MailThread $thread, bool $includeRelations = false): array
    {
        $latest = $thread->relationLoaded('messages') ? $thread->messages->first() : null;

        $summary = [
            'thread_id' => $thread->id,
            'subject' => $thread->subject,
            'lead_id' => $thread->lead_id,
            'order_id' => $thread->order_id,
            'contractor_id' => $thread->contractor_id,
            'mailbox_user_id' => $thread->mailbox_user_id ?? null,
            'last_message_at' => optional($thread->last_message_at)?->toIso8601String(),
            'preview' => $latest !== null
                ? ($latest->retention_summary ?? Str::limit((string) ($latest->body_text ?? ''), 240))
                : null,
        ];

        if ($includeRelations) {
            $summary['last_inbound_at'] = optional($thread->last_inbound_at)?->toIso8601String();
            $summary['last_outbound_at'] = optional($thread->last_outbound_at)?->toIso8601String();
        }

        return $summary;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(MailMessage $message): array
    {
        return [
            'message_id' => $message->id,
            'direction' => $message->direction,
            'from_email' => $message->from_email,
            'to_emails' => $message->to_emails ?? [],
            'cc_emails' => $message->cc_emails ?? [],
            'subject' => $message->subject,
            'body_text' => $message->bodyPurged()
                ? ($message->retention_summary ?? '(тело письма удалено по политике хранения)')
                : $message->body_text,
            'body_purged' => $message->bodyPurged(),
            'sent_at' => optional($message->sent_at)?->toIso8601String(),
        ];
    }
}
