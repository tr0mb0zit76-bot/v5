<?php

namespace App\Services\Mcp;

use App\Models\Lead;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Services\Commercial\MailMailboxAuthorization;
use App\Services\CommercialMailService;
use App\Support\MailSync\MailMessageBodyPresenter;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class MailMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
        private readonly MailMailboxAuthorization $mailboxAuth,
        private readonly CommercialMailService $commercialMail,
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
            'require_contractor_match' => (bool) config('mail_sync.require_contractor_match', true),
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
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @return array<string, mixed>
     */
    public function sendMail(
        User $user,
        string $subject,
        string $body,
        array $toEmails,
        array $ccEmails = [],
        ?int $leadId = null,
        ?int $orderId = null,
    ): array {
        $this->access->requireMailArea($user);

        $lead = $leadId !== null ? Lead::query()->find($leadId) : null;

        $result = $this->commercialMail->sendOutbound(
            subject: $subject,
            bodyText: $body,
            toEmails: $toEmails,
            sender: $user,
            lead: $lead,
            ccEmails: $ccEmails,
            orderId: $orderId,
            contractorId: $lead?->counterparty_id,
        );

        return [
            'thread_id' => $result['thread']->id,
            'message_id' => $result['message']->id,
            'wizard_path' => null,
            'note' => 'Письмо отправлено. Цепочка: thread_id '.$result['thread']->id,
        ];
    }

    /**
     * @param  list<string>|null  $toEmails
     * @param  list<string>  $ccEmails
     * @return array<string, mixed>
     */
    public function replyToThread(
        User $user,
        int $threadId,
        string $body,
        ?array $toEmails = null,
        array $ccEmails = [],
    ): array {
        $this->access->requireMailArea($user);

        $thread = $this->findAccessibleThread($user, $threadId);

        $recipients = $toEmails ?? $this->commercialMail->suggestReplyRecipients($thread, $user);

        if ($recipients === []) {
            throw ValidationException::withMessages([
                'to' => 'Не удалось определить адрес получателя. Укажите to явно.',
            ]);
        }

        $result = $this->commercialMail->replyInThread(
            thread: $thread,
            bodyText: $body,
            toEmails: $recipients,
            sender: $user,
            ccEmails: $ccEmails,
        );

        return [
            'thread_id' => $result['thread']->id,
            'message_id' => $result['message']->id,
            'note' => 'Ответ отправлен в цепочку thread_id '.$result['thread']->id,
        ];
    }

    private function findAccessibleThread(User $user, int $threadId): MailThread
    {
        if (! Schema::hasTable('mail_threads')) {
            throw new ModelNotFoundException('Почта недоступна.');
        }

        $builder = MailThread::query()->whereKey($threadId);
        $this->applyMailboxScope($builder, $user);

        /** @var MailThread|null $thread */
        $thread = $builder->first();

        if ($thread === null) {
            throw new ModelNotFoundException('Цепочка писем не найдена.');
        }

        return $thread;
    }

    /**
     * @param  Builder<MailThread>  $query
     */
    private function applyMailboxScope(Builder $query, User $user): void
    {
        try {
            $this->mailboxAuth->applyThreadScope($query, $user);
        } catch (\Throwable) {
            throw new AuthenticationException('Нет доступа к чужим почтовым ящикам.');
        }
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
                ? MailMessageBodyPresenter::preview($latest)
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
            'body_text' => MailMessageBodyPresenter::plainText($message),
            'body_html' => $message->bodyPurged() ? null : $message->body_html,
            'body_purged' => $message->bodyPurged(),
            'sent_at' => optional($message->sent_at)?->toIso8601String(),
            'attachments' => collect(is_array($message->attachments) ? $message->attachments : [])
                ->map(static function (mixed $attachment): ?string {
                    if (! is_array($attachment)) {
                        return null;
                    }

                    $name = trim((string) ($attachment['original_name'] ?? $attachment['name'] ?? ''));

                    return $name !== '' ? $name : null;
                })
                ->filter()
                ->values()
                ->all(),
        ];
    }
}
