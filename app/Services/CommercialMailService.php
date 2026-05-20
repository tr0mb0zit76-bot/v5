<?php

namespace App\Services;

use App\Mail\CommercialOutboundMail;
use App\Models\Lead;
use App\Models\LeadOffer;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Support\ActivityEventType;
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
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        ?string $attachmentDriver = null,
    ): array {
        abort_unless($this->tablesReady(), 503, 'Почтовый модуль не развёрнут (нет таблиц).');

        $toEmails = array_values(array_filter(array_map('trim', $toEmails)));
        abort_if($toEmails === [], 422, 'Укажите хотя бы один адрес получателя.');

        $fromEmail = (string) config('mail.from.address', $sender->email);
        $now = now();

        $thread = MailThread::query()->create([
            'subject' => $subject,
            'lead_id' => $lead?->id,
            'contractor_id' => $lead?->counterparty_id,
            'lead_offer_id' => $offer?->id,
            'last_message_at' => $now,
            'last_outbound_at' => $now,
            'created_by' => $sender->id,
        ]);

        $message = MailMessage::query()->create([
            'mail_thread_id' => $thread->id,
            'direction' => MailMessage::DIRECTION_OUTBOUND,
            'from_email' => $fromEmail,
            'to_emails' => $toEmails,
            'cc_emails' => $ccEmails === [] ? null : $ccEmails,
            'subject' => $subject,
            'body_text' => $bodyText,
            'sent_at' => $now,
            'lead_offer_id' => $offer?->id,
            'created_by' => $sender->id,
        ]);

        $mailable = new CommercialOutboundMail(
            subjectLine: $subject,
            bodyText: $bodyText,
            attachmentPath: $attachmentPath,
            attachmentName: $attachmentName,
            attachmentDriver: $attachmentDriver,
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
        }

        return ['thread' => $thread, 'message' => $message];
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
        $name = basename($path);

        return [
            'path' => $path,
            'name' => $name !== '' ? $name : 'offer.docx',
            'driver' => null,
        ];
    }

    public function readAttachmentContents(string $path, ?string $driver = null): string
    {
        return $this->documentStorage->get($path, $driver);
    }
}
