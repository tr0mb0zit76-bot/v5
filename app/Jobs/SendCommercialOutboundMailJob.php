<?php

namespace App\Jobs;

use App\Services\CommercialMailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendCommercialOutboundMailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /**
     * @param  list<string>  $toEmails
     * @param  list<string>  $ccEmails
     * @param  list<array{path: string, cid: string, mime: string}>  $inlineImages
     */
    public function __construct(
        public int $mailMessageId,
        public int $senderUserId,
        public array $toEmails,
        public array $ccEmails,
        public ?int $leadOfferId,
        public ?int $leadId,
        public int $mailThreadId,
        public array $inlineImages = [],
    ) {
        $this->onQueue((string) config('async.queues.mail', 'mail'));
    }

    public function handle(CommercialMailService $commercialMail): void
    {
        $commercialMail->deliverQueuedOutbound(
            mailMessageId: $this->mailMessageId,
            senderUserId: $this->senderUserId,
            toEmails: $this->toEmails,
            ccEmails: $this->ccEmails,
            leadOfferId: $this->leadOfferId,
            leadId: $this->leadId,
            mailThreadId: $this->mailThreadId,
            inlineImages: $this->inlineImages,
        );
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('SendCommercialOutboundMailJob failed', [
            'mail_message_id' => $this->mailMessageId,
            'mail_thread_id' => $this->mailThreadId,
            'sender_user_id' => $this->senderUserId,
            'exception' => $exception?->getMessage(),
        ]);
    }
}
