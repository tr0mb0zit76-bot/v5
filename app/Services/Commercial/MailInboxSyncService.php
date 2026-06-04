<?php

namespace App\Services\Commercial;

use App\Models\Lead;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Support\ActivityEventType;
use App\Support\MailSync\ImportedMailMessage;
use App\Support\MailSync\MailImapClient;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class MailInboxSyncService
{
    public function __construct(
        private readonly MailImapClient $imapClient,
        private readonly MailCounterpartyResolver $counterpartyResolver,
        private readonly ActivityLedgerService $activityLedger,
    ) {}

    public function tablesReady(): bool
    {
        return Schema::hasTable('mail_threads')
            && Schema::hasTable('mail_messages')
            && Schema::hasColumn('mail_messages', 'internet_message_id');
    }

    /**
     * @return array{eligible: bool, reasons: list<string>, email: string|null}
     */
    public function syncEligibilityForUserId(int $userId): array
    {
        $user = User::query()->find($userId);

        if ($user === null) {
            return [
                'eligible' => false,
                'reasons' => ['Пользователь с таким ID не найден.'],
                'email' => null,
            ];
        }

        $reasons = $this->syncIneligibilityReasons($user);

        return [
            'eligible' => $reasons === [],
            'reasons' => $reasons,
            'email' => $user->email,
        ];
    }

    /**
     * @return array{imported: int, skipped: int, errors: list<string>, users_processed: int}
     */
    public function syncAllMailboxes(?int $userId = null, ?int $days = null): array
    {
        if (! config('mail_sync.enabled', true)) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['Синхронизация почты отключена (MAIL_SYNC_ENABLED=false).'], 'users_processed' => 0];
        }

        abort_unless($this->tablesReady(), 503, 'Таблицы почты не готовы. Выполните migrate.');

        if (! $this->imapClient->extensionLoaded()) {
            return ['imported' => 0, 'skipped' => 0, 'errors' => ['PHP extension imap не установлена.'], 'users_processed' => 0];
        }

        $days = $days ?? (int) config('mail_sync.initial_sync_days', 30);
        $limit = (int) config('mail_sync.max_messages_per_user', 200);
        $since = CarbonImmutable::now()->subDays(max(1, $days));

        $query = User::query()
            ->where('is_active', true)
            ->where('mail_sync_enabled', true)
            ->whereNotNull('mail_imap_secret');

        if ($userId !== null && $userId > 0) {
            $query->whereKey($userId);
        }

        $users = $query->get();
        $totals = ['imported' => 0, 'skipped' => 0, 'errors' => [], 'users_processed' => 0];

        if ($users->isEmpty() && $userId !== null && $userId > 0) {
            $eligibility = $this->syncEligibilityForUserId($userId);
            $email = $eligibility['email'] ?? "id={$userId}";

            if ($eligibility['reasons'] !== []) {
                $totals['errors'][] = "{$email}: синхронизация пропущена — ".implode(' ', $eligibility['reasons']);
            } else {
                $totals['errors'][] = "{$email}: не попал в очередь sync (проверьте is_active, mail_sync_enabled, mail_imap_secret).";
            }

            return $totals;
        }

        foreach ($users as $user) {
            $totals['users_processed']++;
            try {
                $result = $this->syncUserMailbox($user, $since, $limit);
                $totals['imported'] += $result['imported'];
                $totals['skipped'] += $result['skipped'];

                $user->forceFill([
                    'mail_last_sync_at' => now(),
                    'mail_last_sync_error' => null,
                ])->save();
            } catch (Throwable $exception) {
                $message = Str::limit($exception->getMessage(), 480);
                $totals['errors'][] = "{$user->email}: {$message}";

                $user->forceFill([
                    'mail_last_sync_error' => $message,
                ])->save();
            } finally {
                $this->imapClient->disconnect();
            }
        }

        return $totals;
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    public function syncUserMailbox(User $user, CarbonImmutable $since, int $limit): array
    {
        if (! $user->hasMailImapCredential()) {
            throw new RuntimeException('Пароль почты не задан.');
        }

        $password = $user->mail_imap_secret;

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('Не удалось расшифровать пароль почты.');
        }

        $username = (string) $user->email;
        $imported = 0;
        $skipped = 0;
        $remaining = $limit;
        $folderErrors = [];

        foreach ($this->folderPlan() as $plan) {
            if ($remaining <= 0) {
                break;
            }

            $folderUsed = false;

            foreach ($plan['candidates'] as $folder) {
                if (! is_string($folder) || trim($folder) === '') {
                    continue;
                }

                try {
                    $messages = $this->imapClient->fetchSince(
                        $username,
                        $password,
                        $folder,
                        $plan['direction'],
                        $since,
                        $remaining,
                    );
                    $folderUsed = true;
                } catch (Throwable $exception) {
                    $folderErrors[] = "{$folder}: ".Str::limit($exception->getMessage(), 200);
                    $this->imapClient->disconnect();

                    continue;
                }

                foreach ($messages as $message) {
                    if ($this->messageExists($message->internetMessageId)) {
                        $skipped++;

                        continue;
                    }

                    if ($this->importMessage($user, $message)) {
                        $imported++;
                        $remaining--;
                    } else {
                        $skipped++;
                    }
                }

                $this->imapClient->disconnect();

                if ($folderUsed) {
                    break;
                }
            }
        }

        if ($imported === 0 && $skipped === 0 && $folderErrors !== []) {
            throw new RuntimeException(
                'Не удалось прочитать папки IMAP ('.config('mail_sync.imap.host').'): '.implode('; ', $folderErrors),
            );
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @return list<string>
     */
    private function syncIneligibilityReasons(User $user): array
    {
        $reasons = [];

        if (! $user->is_active) {
            $reasons[] = 'учётная запись неактивна (is_active=0);';
        }

        if (! ($user->mail_sync_enabled ?? true)) {
            $reasons[] = 'синхронизация почты выключена (mail_sync_enabled=0);';
        }

        if (! $user->hasMailImapCredential()) {
            $reasons[] = 'не задан пароль почты (mail_imap_secret) — укажите в карточке пользователя или перелогиньтесь в CRM;';
        }

        return $reasons;
    }

    public function importMessage(User $mailboxUser, ImportedMailMessage $message): ?MailMessage
    {
        if ($this->messageExists($message->internetMessageId)) {
            return null;
        }

        $mailboxEmail = strtolower(trim((string) $mailboxUser->email));
        $participantEmails = array_values(array_unique(array_filter([
            $message->fromEmail,
            ...$message->toEmails,
            ...$message->ccEmails,
        ])));

        $contractorId = $this->counterpartyResolver->resolveContractorIdFromParticipants(
            $participantEmails,
            $mailboxEmail,
        );
        $leadId = $this->counterpartyResolver->resolveOpenLeadId($contractorId);

        $thread = $this->resolveThread($mailboxUser, $message, $contractorId, $leadId);
        $sentAt = $message->sentAt !== null
            ? CarbonImmutable::instance($message->sentAt)
            : now();

        $mailMessage = MailMessage::query()->create([
            'mail_thread_id' => $thread->id,
            'direction' => $message->direction,
            'internet_message_id' => $message->internetMessageId,
            'from_email' => $message->fromEmail,
            'to_emails' => $message->toEmails === [] ? [$mailboxEmail] : $message->toEmails,
            'cc_emails' => $message->ccEmails === [] ? null : $message->ccEmails,
            'subject' => $message->subject !== '' ? $message->subject : '(без темы)',
            'body_text' => $message->bodyText,
            'sent_at' => $sentAt,
            'mailbox_user_id' => $mailboxUser->id,
            'created_by' => $mailboxUser->id,
        ]);

        $threadUpdates = [
            'last_message_at' => $sentAt,
            'contractor_id' => $thread->contractor_id ?? $contractorId,
            'lead_id' => $thread->lead_id ?? $leadId,
        ];

        if ($message->direction === MailMessage::DIRECTION_INBOUND) {
            $threadUpdates['last_inbound_at'] = $sentAt;
        } else {
            $threadUpdates['last_outbound_at'] = $sentAt;
        }

        $thread->forceFill($threadUpdates)->save();

        if ($leadId !== null && $message->direction === MailMessage::DIRECTION_INBOUND) {
            $lead = Lead::query()->find($leadId);

            if ($lead !== null) {
                $this->activityLedger->record(
                    $lead,
                    ActivityEventType::EmailInbound,
                    'Входящее письмо',
                    Str::limit((string) ($message->bodyText ?? $message->subject), 240),
                    [
                        'mail_thread_id' => $thread->id,
                        'mail_message_id' => $mailMessage->id,
                        'from' => $message->fromEmail,
                        'subject' => $message->subject,
                        'mailbox_user_id' => $mailboxUser->id,
                    ],
                    $sentAt,
                    $mailboxUser,
                    $mailMessage,
                );
            }
        }

        return $mailMessage;
    }

    /**
     * @return list<array{direction: string, candidates: list<string>}>
     */
    private function folderPlan(): array
    {
        return [
            [
                'direction' => MailMessage::DIRECTION_INBOUND,
                'candidates' => config('mail_sync.folders.inbound', ['INBOX']),
            ],
            [
                'direction' => MailMessage::DIRECTION_OUTBOUND,
                'candidates' => config('mail_sync.folders.outbound', ['Sent']),
            ],
        ];
    }

    private function messageExists(string $internetMessageId): bool
    {
        return MailMessage::query()
            ->where('internet_message_id', $internetMessageId)
            ->exists();
    }

    private function resolveThread(
        User $mailboxUser,
        ImportedMailMessage $message,
        ?int $contractorId,
        ?int $leadId,
    ): MailThread {
        if ($message->inReplyTo !== null && $message->inReplyTo !== '') {
            $parent = MailMessage::query()
                ->where('internet_message_id', $message->inReplyTo)
                ->first();

            if ($parent !== null) {
                return $parent->thread;
            }
        }

        $normalizedSubject = $this->normalizeSubject($message->subject);

        if ($normalizedSubject !== '') {
            $existing = MailThread::query()
                ->where('mailbox_user_id', $mailboxUser->id)
                ->where('last_message_at', '>=', now()->subDays(120))
                ->orderByDesc('last_message_at')
                ->limit(40)
                ->get()
                ->first(fn (MailThread $thread): bool => strcasecmp(
                    $this->normalizeSubject((string) $thread->subject),
                    $normalizedSubject,
                ) === 0);

            if ($existing !== null) {
                return $existing;
            }
        }

        return MailThread::query()->create([
            'subject' => $message->subject !== '' ? $message->subject : '(без темы)',
            'lead_id' => $leadId,
            'contractor_id' => $contractorId,
            'mailbox_user_id' => $mailboxUser->id,
            'last_message_at' => $message->sentAt ?? now(),
            'last_inbound_at' => $message->direction === MailMessage::DIRECTION_INBOUND ? ($message->sentAt ?? now()) : null,
            'last_outbound_at' => $message->direction === MailMessage::DIRECTION_OUTBOUND ? ($message->sentAt ?? now()) : null,
            'created_by' => $mailboxUser->id,
        ]);
    }

    private function normalizeSubject(string $subject): string
    {
        $subject = trim($subject);

        while (preg_match('/^(re|fwd|fw):\s*/iu', $subject) === 1) {
            $subject = preg_replace('/^(re|fwd|fw):\s*/iu', '', $subject) ?? $subject;
            $subject = trim($subject);
        }

        return $subject;
    }
}
