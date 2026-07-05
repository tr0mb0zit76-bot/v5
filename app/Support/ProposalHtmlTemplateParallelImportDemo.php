<?php

namespace App\Support;

/**
 * Демо-шаблон КП по макету Unisender «Параллельный импорт» (без рассылки).
 */
class ProposalHtmlTemplateParallelImportDemo
{
    public const SLUG = 'parallel-import-demo';

    public const NAME = 'Параллельный импорт — холодное письмо';

    public static function htmlBody(): string
    {
        return ProposalHtmlTemplateColdEmailLibrary::htmlBody(self::SLUG);
    }

    public static function cssInline(): string
    {
        return ProposalHtmlTemplateColdEmailLibrary::cssInline();
    }
}
