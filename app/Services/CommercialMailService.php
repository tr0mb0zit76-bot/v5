<?php

namespace App\Services;

use App\Jobs\SendCommercialOutboundMailJob;
use App\Mail\CommercialOutboundMail;
use App\Models\Lead;
use App\Models\LeadOffer;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Order;
use App\Models\User;
use App\Support\ActivityEventType;
use App\Support\MailSync\OutboundMailMessageId;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class CommercialMailService
{
    public function __construct(
        private readonly ActivityLedgerService $activityLedger,
        private readonly DocumentStorageService $documentStorage,
    ) {}

    public function tablesReady(): bool
    {
        return Schema::hasTable('mail_threads') && Schema::hasTable('mail_messages');
    }

    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @param  list<array{path: string, name: string, driver: string|null, mime_type: string|null, file_size?: int}>  $attachments
     * @param  list<array{path: string, cid: string, mime: string}>  $inlineImages
     * @return array{thread: MailThread, message: MailMessage}
     */
    public function sendOutbound(
        string $subject,
        string $bodyText,
        array $toEmails,
        User $sender,
        ?Lead $lead = null,
        ?LeadOffer $offer = null,
        array $ccEmails = [],
        array $attachments = [],
        ?MailThread $existingThread = null,
        ?MailMessage $inReplyToMessage = null,
        ?int $orderId = null,
        ?int $contractorId = null,
        ?string $bodyHtml = null,
        array $inlineImages = [],
    ): array {
        abort_unless($this->tablesReady(), 503, 'Почтовый модуль не развёрнут (нет таблиц).');

        $toEmails = array_values(array_filter(array_map('trim', $toEmails)));
        abort_if($toEmails === [], 422, 'Укажите хотя бы один адрес получателя.');

        $ccEmails = array_values(array_filter(array_map('trim', $ccEmails)));
        $from = $this->resolveSenderFrom($sender);
        $now = now();
        $internetMessageId = OutboundMailMessageId::generate($from['email']);
        $inReplyToHeader = $this->resolveInReplyToHeader($inReplyToMessage, $existingThread);
        $referencesHeader = $inReplyToHeader;

        if ($existingThread !== null) {
            $thread = $existingThread;
            $subject = $this->replySubject((string) $thread->subject);

            $threadUpdates = [
                'last_message_at' => $now,
                'last_outbound_at' => $now,
            ];

            if ($thread->mailbox_user_id === null) {
                $threadUpdates['mailbox_user_id'] = $sender->id;
            }

            if ($lead !== null && $thread->lead_id === null) {
                $threadUpdates['lead_id'] = $lead->id;
                $threadUpdates['contractor_id'] = $lead->counterparty_id;
            }

            if ($orderId !== null && $thread->order_id === null) {
                $threadUpdates['order_id'] = $orderId;
            }

            if ($contractorId !== null && $thread->contractor_id === null) {
                $threadUpdates['contractor_id'] = $contractorId;
            }

            $thread->forceFill($threadUpdates)->save();
        } else {
            $thread = MailThread::query()->create([
                'subject' => $subject,
                'lead_id' => $lead?->id,
                'order_id' => $orderId,
                'contractor_id' => $contractorId ?? $lead?->counterparty_id,
                'lead_offer_id' => $offer?->id,
                'last_message_at' => $now,
                'last_outbound_at' => $now,
                'mailbox_user_id' => $sender->id,
                'created_by' => $sender->id,
            ]);
        }

        $messageAttributes = [
            'mail_thread_id' => $thread->id,
            'direction' => MailMessage::DIRECTION_OUTBOUND,
            'from_email' => $from['email'],
            'to_emails' => $toEmails,
            'cc_emails' => $ccEmails === [] ? null : $ccEmails,
            'subject' => $subject,
            'body_text' => $bodyText,
            'sent_at' => $now,
            'lead_offer_id' => $offer?->id,
            'created_by' => $sender->id,
            'mailbox_user_id' => $sender->id,
        ];

        if (Schema::hasColumn('mail_messages', 'body_html') && $bodyHtml !== null && trim($bodyHtml) !== '') {
            $messageAttributes['body_html'] = $bodyHtml;
        }

        if (Schema::hasColumn('mail_messages', 'internet_message_id')) {
            $messageAttributes['internet_message_id'] = $internetMessageId;
        }

        if (Schema::hasColumn('mail_messages', 'attachments') && $attachments !== []) {
            $messageAttributes['attachments'] = $this->serializeAttachmentsForStorage($attachments);
        }

        $message = MailMessage::query()->create($messageAttributes);

        $mailable = new CommercialOutboundMail(
            subjectLine: $subject,
            bodyText: $bodyText,
            fromEmail: $from['email'],
            fromName: $from['name'],
            messageId: $internetMessageId,
            inReplyTo: $inReplyToHeader,
            references: $referencesHeader,
            outboundAttachments: $this->normalizeAttachmentsForMailable($attachments),
            bodyHtml: $bodyHtml,
            inlineImages: $inlineImages,
        );

        $this->dispatchOutboundDelivery(
            sender: $sender,
            mailable: $mailable,
            toEmails: $toEmails,
            ccEmails: $ccEmails,
            message: $message,
            thread: $thread,
            lead: $lead,
            offer: $offer,
            bodyText: $bodyText,
            subject: $subject,
            sentAt: $now,
            inlineImages: $inlineImages,
        );

        return ['thread' => $thread, 'message' => $message];
    }

    /**
     * Доставка исходящего письма из очереди (вызывается SendCommercialOutboundMailJob).
     *
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @param  list<array{path: string, cid: string, mime: string}>  $inlineImages
     */
    public function deliverQueuedOutbound(
        int $mailMessageId,
        int $senderUserId,
        array $toEmails,
        array $ccEmails,
        ?int $leadOfferId,
        ?int $leadId,
        int $mailThreadId,
        array $inlineImages = [],
    ): void {
        abort_unless($this->tablesReady(), 503, 'Почтовый модуль не развёрнут (нет таблиц).');

        $message = MailMessage::query()->findOrFail($mailMessageId);
        $thread = MailThread::query()->findOrFail($mailThreadId);
        $sender = User::query()->findOrFail($senderUserId);
        $lead = $leadId !== null ? Lead::query()->find($leadId) : null;
        $offer = $leadOfferId !== null ? LeadOffer::query()->find($leadOfferId) : null;

        $from = $this->resolveSenderFrom($sender);
        $inReplyToHeader = $this->resolveInReplyToHeader(null, $thread);

        $mailable = new CommercialOutboundMail(
            subjectLine: (string) $message->subject,
            bodyText: (string) $message->body_text,
            fromEmail: $from['email'],
            fromName: $from['name'],
            messageId: (string) ($message->internet_message_id ?? OutboundMailMessageId::generate($from['email'])),
            inReplyTo: $inReplyToHeader,
            references: $inReplyToHeader,
            outboundAttachments: $this->attachmentsForMailableFromStorage($message->attachments),
            bodyHtml: is_string($message->body_html) ? $message->body_html : null,
            inlineImages: $inlineImages,
        );

        $this->deliverMailable($sender, $mailable, $toEmails, $ccEmails);

        $sentAt = $message->sent_at ?? now();

        $this->finalizeOutboundDelivery(
            thread: $thread,
            lead: $lead,
            offer: $offer,
            bodyText: (string) $message->body_text,
            toEmails: $toEmails,
            subject: (string) $message->subject,
            sentAt: $sentAt,
            sender: $sender,
            message: $message,
        );
    }

    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @param  list<array{path: string, cid: string, mime: string}>  $inlineImages
     */
    private function dispatchOutboundDelivery(
        User $sender,
        CommercialOutboundMail $mailable,
        array $toEmails,
        array $ccEmails,
        MailMessage $message,
        MailThread $thread,
        ?Lead $lead,
        ?LeadOffer $offer,
        string $bodyText,
        string $subject,
        Carbon $sentAt,
        array $inlineImages,
    ): void {
        if ($this->shouldQueueOutboundMail()) {
            SendCommercialOutboundMailJob::dispatch(
                mailMessageId: $message->id,
                senderUserId: $sender->id,
                toEmails: $toEmails,
                ccEmails: $ccEmails,
                leadOfferId: $offer?->id,
                leadId: $lead?->id,
                mailThreadId: $thread->id,
                inlineImages: $inlineImages,
            );

            return;
        }

        $this->deliverMailable($sender, $mailable, $toEmails, $ccEmails);

        $this->finalizeOutboundDelivery(
            thread: $thread,
            lead: $lead,
            offer: $offer,
            bodyText: $bodyText,
            toEmails: $toEmails,
            subject: $subject,
            sentAt: $sentAt,
            sender: $sender,
            message: $message,
        );
    }

    private function shouldQueueOutboundMail(): bool
    {
        return (bool) config('async.outbound_mail')
            && (string) config('queue.default') !== 'sync';
    }

    /**
     * @param  list<string>  $toEmails
     */
    private function finalizeOutboundDelivery(
        MailThread $thread,
        ?Lead $lead,
        ?LeadOffer $offer,
        string $bodyText,
        array $toEmails,
        string $subject,
        Carbon $sentAt,
        User $sender,
        MailMessage $message,
    ): void {
        if ($offer !== null) {
            $offer->forceFill([
                'status' => 'sent',
                'sent_at' => $sentAt,
                'last_mail_thread_id' => $thread->id,
            ])->save();

            if ($lead !== null) {
                $lead->forceFill([
                    'proposal_sent_at' => $sentAt,
                    'status' => $lead->status === 'proposal_ready' ? 'proposal_sent' : $lead->status,
                ])->save();
            }
        }

        $this->recordOutboundActivity($thread, $lead, $offer, $bodyText, $toEmails, $subject, $sentAt, $sender, $message);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $storedAttachments
     * @return list<array{path: string, name: string, driver: string|null, mime_type: string|null}>
     */
    private function attachmentsForMailableFromStorage(?array $storedAttachments): array
    {
        if ($storedAttachments === null || $storedAttachments === []) {
            return [];
        }

        return array_values(array_map(
            static fn (array $attachment): array => [
                'path' => (string) ($attachment['file_path'] ?? $attachment['path'] ?? ''),
                'name' => (string) ($attachment['original_name'] ?? $attachment['name'] ?? 'attachment'),
                'driver' => isset($attachment['storage_driver']) ? (string) $attachment['storage_driver'] : ($attachment['driver'] ?? null),
                'mime_type' => isset($attachment['mime_type']) ? (string) $attachment['mime_type'] : null,
            ],
            $storedAttachments,
        ));
    }

    /**
     * @param  list<UploadedFile>  $uploadedFiles
     * @return list<array{path: string, name: string, driver: string|null, mime_type: string|null, file_size: int}>
     */
    public function storeUploadedAttachments(array $uploadedFiles, User $sender, ?int $orderId = null): array
    {
        $stored = [];

        foreach ($uploadedFiles as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $meta = $this->documentStorage->storeMailOutboundUpload($file, $sender->id, $orderId);

            $stored[] = [
                'path' => $meta['file_path'],
                'name' => $meta['original_name'],
                'driver' => $meta['storage_driver'],
                'mime_type' => $meta['mime_type'],
                'file_size' => $meta['file_size'],
            ];
        }

        return $stored;
    }

    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @param  list<array{path: string, name: string, driver: string|null, mime_type: string|null, file_size?: int}>  $attachments
     * @return array{thread: MailThread, message: MailMessage}
     */
    public function replyInThread(
        MailThread $thread,
        string $bodyText,
        array $toEmails,
        User $sender,
        array $ccEmails = [],
        array $attachments = [],
    ): array {
        $lead = $thread->lead_id !== null
            ? Lead::query()->find($thread->lead_id)
            : null;

        $latestMessage = $thread->messages()
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        return $this->sendOutbound(
            subject: (string) $thread->subject,
            bodyText: $bodyText,
            toEmails: $toEmails,
            sender: $sender,
            lead: $lead,
            ccEmails: $ccEmails,
            attachments: $attachments,
            existingThread: $thread,
            inReplyToMessage: $latestMessage,
            orderId: $thread->order_id,
            contractorId: $thread->contractor_id,
        );
    }

    /**
     * @return list<string>
     */
    public function suggestReplyRecipients(MailThread $thread, User $mailboxUser): array
    {
        $mailbox = strtolower(trim((string) $mailboxUser->email));

        $messages = $thread->messages()
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->get();

        foreach ($messages as $message) {
            if ($message->direction === MailMessage::DIRECTION_INBOUND) {
                $from = strtolower(trim((string) $message->from_email));

                if ($from !== '' && $from !== $mailbox && filter_var($from, FILTER_VALIDATE_EMAIL)) {
                    return [$from];
                }
            }
        }

        foreach ($messages as $message) {
            if ($message->direction === MailMessage::DIRECTION_OUTBOUND) {
                foreach ($message->to_emails ?? [] as $email) {
                    $normalized = strtolower(trim((string) $email));

                    if ($normalized !== '' && $normalized !== $mailbox && filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                        return [$normalized];
                    }
                }
            }
        }

        return [];
    }

    /**
     * @return array{path: string, name: string, driver: string|null}|null
     */
    public function resolveOfferAttachment(LeadOffer $offer): ?array
    {
        if (blank($offer->generated_file_path)) {
            return null;
        }

        $path = (string) $offer->generated_file_path;
        $payload = is_array($offer->payload) ? $offer->payload : [];
        $contentType = (string) ($payload['content_type'] ?? '');
        $name = basename($path);
        $defaultName = str_ends_with(strtolower($path), '.pdf') || $contentType === 'application/pdf'
            ? 'offer.pdf'
            : 'offer.docx';

        return [
            'path' => $path,
            'name' => $name !== '' ? $name : $defaultName,
            'driver' => (string) ($payload['generated_disk'] ?? null) ?: null,
            'mime_type' => $contentType !== ''
                ? $contentType
                : (str_ends_with(strtolower($path), '.pdf') ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];
    }

    public function readAttachmentContents(string $path, ?string $driver = null): string
    {
        return $this->documentStorage->get($path, $driver);
    }

    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     */
    private function deliverMailable(User $sender, CommercialOutboundMail $mailable, array $toEmails, array $ccEmails): void
    {
        if (! $this->usesSmtpTransport()) {
            Mail::to($toEmails)->cc($ccEmails)->send($mailable);

            return;
        }

        abort_unless(
            $sender->hasMailImapCredential(),
            422,
            'Не задан пароль почты. Укажите его в карточке пользователя (тот же, что для IMAP) или перелогиньтесь в CRM.',
        );

        $password = $sender->mail_imap_secret;
        abort_if(! is_string($password) || $password === '', 422, 'Не удалось прочитать пароль почты. Перелогиньтесь или задайте пароль заново.');

        $from = $this->resolveSenderFrom($sender);
        $previousUsername = config('mail.mailers.smtp.username');
        $previousPassword = config('mail.mailers.smtp.password');

        config([
            'mail.mailers.smtp.username' => $from['email'],
            'mail.mailers.smtp.password' => $password,
        ]);
        Mail::purge('smtp');

        try {
            Mail::mailer('smtp')->to($toEmails)->cc($ccEmails)->send($mailable);
        } finally {
            config([
                'mail.mailers.smtp.username' => $previousUsername,
                'mail.mailers.smtp.password' => $previousPassword,
            ]);
            Mail::purge('smtp');
        }
    }

    private function usesSmtpTransport(): bool
    {
        $name = (string) config('mail.default');
        $transport = (string) config("mail.mailers.{$name}.transport", '');

        return $name === 'smtp' || $transport === 'smtp';
    }

    /**
     * @return array{email: string, name: string}
     */
    private function resolveSenderFrom(User $sender): array
    {
        $email = filter_var($sender->email, FILTER_VALIDATE_EMAIL)
            ? strtolower(trim((string) $sender->email))
            : null;

        if ($email === null) {
            $email = strtolower(trim((string) config('mail.from.address', 'hello@example.com')));
        }

        $name = trim((string) ($sender->name ?? ''));

        if ($name === '') {
            $name = (string) config('mail.from.name', config('app.name', 'CRM'));
        }

        return [
            'email' => $email,
            'name' => $name,
        ];
    }

    private function replySubject(string $subject): string
    {
        $subject = trim($subject);

        if ($subject === '') {
            return 'Re: (без темы)';
        }

        if (preg_match('/^re:\s/iu', $subject) === 1) {
            return $subject;
        }

        return 'Re: '.$subject;
    }

    private function resolveInReplyToHeader(?MailMessage $inReplyToMessage, ?MailThread $thread): ?string
    {
        if ($inReplyToMessage !== null && filled($inReplyToMessage->internet_message_id ?? null)) {
            return (string) $inReplyToMessage->internet_message_id;
        }

        if ($thread === null) {
            return null;
        }

        $fallback = $thread->messages()
            ->whereNotNull('internet_message_id')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->value('internet_message_id');

        return is_string($fallback) && $fallback !== '' ? $fallback : null;
    }

    /**
     * @param  list<string>  $toEmails
     */
    private function recordOutboundActivity(
        MailThread $thread,
        ?Lead $lead,
        ?LeadOffer $offer,
        string $bodyText,
        array $toEmails,
        string $subject,
        Carbon $now,
        User $sender,
        MailMessage $message,
    ): void {
        if ($lead !== null) {
            $this->activityLedger->record(
                $lead,
                $offer !== null ? ActivityEventType::OfferSent : ActivityEventType::EmailOutbound,
                $offer !== null ? 'КП отправлено по e-mail' : 'Исходящее письмо',
                Str::limit($bodyText, 240),
                [
                    'mail_thread_id' => $thread->id,
                    'mail_message_id' => $message->id,
                    'to' => $toEmails,
                    'subject' => $subject,
                ],
                $now,
                $sender,
                $message,
            );

            return;
        }

        if ($thread->order_id !== null) {
            $order = Order::query()->find($thread->order_id);

            if ($order !== null) {
                $this->activityLedger->record(
                    $order,
                    ActivityEventType::EmailOutbound,
                    'Исходящее письмо',
                    Str::limit($bodyText, 240),
                    [
                        'mail_thread_id' => $thread->id,
                        'mail_message_id' => $message->id,
                        'to' => $toEmails,
                        'subject' => $subject,
                    ],
                    $now,
                    $sender,
                    $message,
                );
            }
        }
    }

    /**
     * @param  list<array{path: string, name: string, driver: string|null, mime_type: string|null, file_size?: int}>  $attachments
     * @return list<array{original_name: string, file_path: string, storage_driver: string|null, mime_type: string|null, file_size: int|null}>
     */
    private function serializeAttachmentsForStorage(array $attachments): array
    {
        return array_values(array_map(
            static fn (array $attachment): array => [
                'original_name' => (string) $attachment['name'],
                'file_path' => (string) $attachment['path'],
                'storage_driver' => $attachment['driver'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
                'file_size' => isset($attachment['file_size']) ? (int) $attachment['file_size'] : null,
            ],
            $attachments,
        ));
    }

    /**
     * @param  list<array{path: string, name: string, driver: string|null, mime_type: string|null, file_size?: int}>  $attachments
     * @return list<array{path: string, name: string, driver: string|null, mime_type: string|null}>
     */
    private function normalizeAttachmentsForMailable(array $attachments): array
    {
        return array_values(array_map(
            static fn (array $attachment): array => [
                'path' => (string) $attachment['path'],
                'name' => (string) $attachment['name'],
                'driver' => $attachment['driver'] ?? null,
                'mime_type' => $attachment['mime_type'] ?? null,
            ],
            $attachments,
        ));
    }
}
