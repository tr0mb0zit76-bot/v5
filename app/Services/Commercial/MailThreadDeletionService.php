<?php

namespace App\Services\Commercial;

use App\Models\LeadOffer;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Services\DocumentStorageService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class MailThreadDeletionService
{
    public function __construct(
        private readonly DocumentStorageService $documentStorage,
    ) {}

    public function delete(MailThread $thread): void
    {
        DB::transaction(function () use ($thread): void {
            $messages = MailMessage::query()
                ->where('mail_thread_id', $thread->id)
                ->get(['id', 'attachments']);

            foreach ($messages as $message) {
                $this->deleteMessageAttachments($message);
            }

            MailMessage::query()
                ->where('mail_thread_id', $thread->id)
                ->delete();

            if (Schema::hasTable('lead_offers') && Schema::hasColumn('lead_offers', 'last_mail_thread_id')) {
                LeadOffer::query()
                    ->where('last_mail_thread_id', $thread->id)
                    ->update(['last_mail_thread_id' => null]);
            }

            $thread->delete();
        });
    }

    private function deleteMessageAttachments(MailMessage $message): void
    {
        $attachments = $message->attachments;

        if (! is_array($attachments)) {
            return;
        }

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $path = trim((string) ($attachment['file_path'] ?? ''));

            if ($path === '') {
                continue;
            }

            $driver = isset($attachment['storage_driver']) ? (string) $attachment['storage_driver'] : null;

            $this->documentStorage->delete($path, $driver);
        }
    }
}
