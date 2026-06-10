<?php

namespace App\Support\MailSync;

use App\Models\MailMessage;

final class MailImportAllowance
{
    public function shouldImport(
        ImportedMailMessage $message,
        string $mailboxEmail,
        ?MailContractorAllowlist $allowlist = null,
    ): bool {
        if ($this->isBlockedSender($message)) {
            return false;
        }

        if (! config('mail_sync.require_contractor_match', false)) {
            return true;
        }

        $allowlist ??= MailContractorAllowlist::cached();

        if ($allowlist->isEmpty()) {
            return false;
        }

        if ($this->isReplyToKnownMessage($message)) {
            return true;
        }

        $participantEmails = array_values(array_unique(array_filter([
            $message->fromEmail,
            ...$message->toEmails,
            ...$message->ccEmails,
        ])));

        return $allowlist->allowsAnyParticipant($participantEmails, $mailboxEmail);
    }

    private function isBlockedSender(ImportedMailMessage $message): bool
    {
        if ($message->direction !== MailMessage::DIRECTION_INBOUND) {
            return false;
        }

        return MailSyncSpamBlocklist::isBlocked($message->fromEmail);
    }

    private function isReplyToKnownMessage(ImportedMailMessage $message): bool
    {
        $inReplyTo = trim((string) ($message->inReplyTo ?? ''));

        if ($inReplyTo === '') {
            return false;
        }

        return MailMessage::query()
            ->where('internet_message_id', $inReplyTo)
            ->exists();
    }
}
