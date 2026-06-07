<?php

namespace App\Support\MailSync;

use App\Models\MailMessage;
use Illuminate\Support\Str;

final class MailMessageBodyPresenter
{
    public static function plainText(MailMessage $message): ?string
    {
        if ($message->bodyPurged()) {
            return $message->retention_summary ?? '(тело письма удалено по политике хранения)';
        }

        $fromHtml = MailHtmlSanitizer::toPlainText($message->body_html);

        if ($fromHtml !== '') {
            return $fromHtml;
        }

        $fromText = MailHtmlSanitizer::cleanPlainText(trim((string) ($message->body_text ?? '')));

        if ($fromText !== '') {
            return $fromText;
        }

        $raw = trim((string) ($message->body_text ?? ''));

        return $raw !== '' ? $raw : null;
    }

    public static function preview(MailMessage $message, int $limit = 240): ?string
    {
        if ($message->bodyPurged()) {
            $summary = trim((string) ($message->retention_summary ?? ''));

            return $summary !== '' ? Str::limit($summary, $limit) : null;
        }

        $plain = self::plainText($message);

        return $plain !== null && $plain !== '' ? Str::limit($plain, $limit) : null;
    }
}
