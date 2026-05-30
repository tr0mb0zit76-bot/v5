<?php

namespace App\Support;

use League\HTMLToMarkdown\HtmlConverter;

/**
 * Книга продаж хранит markdown; иногда в БД попадает HTML (вставка из Word, старый режим редактора).
 * Нормализуем в markdown, блок ссылок на дочерние страницы сохраняем как есть.
 */
final class SalesBookContentNormalizer
{
    public const CHILD_LINKS_START = '<!-- sales-book:child-links -->';

    public const CHILD_LINKS_END = '<!-- /sales-book:child-links -->';

    public function normalize(string $content): string
    {
        $content = str_replace("\r\n", "\n", $content);

        [$body, $childLinksBlock] = $this->extractChildLinksBlock($content);
        $normalizedBody = $this->normalizeBody($body);

        if ($childLinksBlock === '') {
            return $normalizedBody;
        }

        if ($normalizedBody === '') {
            return ltrim($childLinksBlock, "\n");
        }

        return rtrim($normalizedBody).$childLinksBlock;
    }

    /**
     * Контент для редактора: без автоблока ссылок на дочерние страницы (сервер добавит при сохранении).
     */
    public function forEditor(string $content): string
    {
        [$body] = $this->extractChildLinksBlock($this->normalize($content));

        return $body;
    }

    private function normalizeBody(string $body): string
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            return '';
        }

        if (! $this->bodyContainsHtmlMarkup($trimmed)) {
            return rtrim($body);
        }

        $converter = new HtmlConverter([
            'strip_tags' => false,
            'hard_break' => true,
            'strip_placeholder_links' => false,
        ]);

        return trim($converter->convert($trimmed));
    }

    private function bodyContainsHtmlMarkup(string $body): bool
    {
        if (preg_match('/<(p|div|span|ul|ol|li|h[1-6]|strong|b|em|i|u|blockquote|pre|code|table|thead|tbody|tr|td|th|br|img|a)(\s|\/?>)/i', $body) === 1) {
            return true;
        }

        return preg_match('/<ul[^>]*data-type/i', $body) === 1;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function extractChildLinksBlock(string $content): array
    {
        $pattern = '/\n?'.preg_quote(self::CHILD_LINKS_START, '/').'.*?'.preg_quote(self::CHILD_LINKS_END, '/').'/s';

        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return [$content, ''];
        }

        $block = $matches[0][0];
        $body = str_replace($block, '', $content);

        return [$body, $block];
    }
}
