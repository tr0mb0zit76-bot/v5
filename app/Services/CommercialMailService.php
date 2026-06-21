<?php

namespace App\Services;

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
        );

        Mail::to($toEmails)->cc($ccEmails)->send($mailable);

        if ($offer !== null) {
            $offer->forceFill([
                'status' => 'sent',
                'sent_at' => $now,
                'last_mail_thread_id' => $thread->id,
            ])->save();

            if ($lead !== null) {
                $lead->forceFill([
                    'proposal_sent_at' => $now,
                    'status' => $lead->status === 'proposal_ready' ? 'proposal_sent' : $lead->status,
                ])->save();
            }
        }

        $this->recordOutboundActivity($thread, $lead, $offer, $bodyText, $toEmails, $subject, $now, $sender, $message);

        return ['thread' => $thread, 'message' => $message];
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
