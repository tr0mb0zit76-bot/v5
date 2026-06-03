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

    public function __construct(
        private readonly SalesBookQuizParser $quizParser,
    ) {}

    public function normalize(string $content): string
    {
        $content = $this->stripBom(str_replace("\r\n", "\n", $content));
        $content = str_replace("\r", "\n", $content);

        [$body, $childLinksBlock] = $this->extractChildLinksBlock($content);
        [$body, $quizBlock] = $this->extractQuizBlock($body);
        $normalizedBody = $this->normalizeBody($body);

        $result = $normalizedBody;

        if ($quizBlock !== '') {
            $result = $result === '' ? ltrim($quizBlock, "\n") : rtrim($result).$quizBlock;
        }

        if ($childLinksBlock === '') {
            return $result;
        }

        if ($result === '') {
            return ltrim($childLinksBlock, "\n");
        }

        return rtrim($result).$childLinksBlock;
    }

    /**
     * @return array{
     *     questions: list<array{
     *         id: string,
     *         text: string,
     *         options: list<array{id: string, text: string}>,
     *         correct: string,
     *         explanation: string|null
     *     }>
     * }|null
     */
    public function parseQuiz(string $content): ?array
    {
        return $this->quizParser->parse($content);
    }

    /**
     * Сохраняет скрытый quiz-блок при сохранении из редактора, если он не был изменён вручную.
     */
    public function preserveQuizBlock(string $incomingContent, string $existingContent): string
    {
        if (str_contains($incomingContent, SalesBookQuizParser::START_MARKER)) {
            return $incomingContent;
        }

        [, $quizBlock] = $this->extractQuizBlock($existingContent);

        if ($quizBlock === '') {
            return $incomingContent;
        }

        if (trim($incomingContent) === '') {
            return ltrim($quizBlock, "\n");
        }

        return rtrim($incomingContent).$quizBlock;
    }

    /**
     * Контент для редактора: без автоблока ссылок на дочерние страницы (сервер добавит при сохранении).
     */
    public function forEditor(string $content): string
    {
        [$body] = $this->extractChildLinksBlock($this->normalize($content));

        return $this->quizParser->stripBlock($body);
    }

    /**
     * Контент для просмотра: тело страницы и ссылки на дочерние страницы как обычный markdown-список.
     */
    public function forReader(string $content): string
    {
        $normalized = $this->normalize($content);
        [$body, $childLinksBlock] = $this->extractChildLinksBlock($normalized);
        $body = $this->quizParser->stripBlock($body);

        if ($childLinksBlock === '') {
            return $body;
        }

        $listMarkdown = trim((string) preg_replace(
            '/\s*'.preg_quote(self::CHILD_LINKS_START, '/').'\s*|\s*'.preg_quote(self::CHILD_LINKS_END, '/').'\s*/s',
            '',
            $childLinksBlock,
        ));

        if ($listMarkdown === '') {
            return $body;
        }

        if ($body === '') {
            return $listMarkdown;
        }

        return rtrim($body)."\n\n".$listMarkdown;
    }

    private function normalizeBody(string $body): string
    {
        $trimmed = trim($body);

        if ($trimmed === '') {
            return '';
        }

        if (! $this->bodyContainsHtmlMarkup($trimmed)) {
            return rtrim($this->replaceUnsupportedMarkdownImages($this->normalizeMarkdownTables($body)));
        }

        $converter = new HtmlConverter([
            'strip_tags' => false,
            'hard_break' => true,
            'strip_placeholder_links' => false,
        ]);

        return rtrim($this->replaceUnsupportedMarkdownImages($this->normalizeMarkdownTables(trim($converter->convert($trimmed)))));
    }

    /**
     * MarkText и другие редакторы сохраняют локальные пути (C:\..., file://).
     * Браузер их блокирует — заменяем на текстовую подсказку.
     */
    private function replaceUnsupportedMarkdownImages(string $markdown): string
    {
        if (! str_contains($markdown, '![')) {
            return $markdown;
        }

        return (string) preg_replace_callback(
            '/!\[([^\]]*)\]\(([^)]+)\)/u',
            function (array $matches): string {
                $alt = trim($matches[1]) !== '' ? trim($matches[1]) : 'Изображение';
                $url = trim($matches[2]);

                if ($this->isSupportedMarkdownImageUrl($url)) {
                    return $matches[0];
                }

                return '> ⚠ '.$alt.': загрузите изображение через кнопку «Картинка» в редакторе.';
            },
            $markdown,
        );
    }

    private function isSupportedMarkdownImageUrl(string $url): bool
    {
        if ($url === '') {
            return false;
        }

        if (preg_match('#^https?://#i', $url) === 1) {
            return true;
        }

        if (str_starts_with($url, '/')) {
            return true;
        }

        return str_contains($url, 'sales-assistant/book/assets');
    }

    /**
     * GFM-таблицы должны идти подряд; пустые строки между |...| ломают разбор в редакторе.
     */
    private function normalizeMarkdownTables(string $markdown): string
    {
        if ($markdown === '' || ! str_contains($markdown, '|')) {
            return $markdown;
        }

        $lines = explode("\n", str_replace("\r\n", "\n", $markdown));
        $result = [];
        $index = 0;
        $lineCount = count($lines);

        while ($index < $lineCount) {
            $line = $lines[$index];

            if (! $this->isMarkdownTableRow($line)) {
                $result[] = $line;
                $index++;

                continue;
            }

            while ($index < $lineCount) {
                $current = $lines[$index];

                if (trim($current) === '') {
                    $nextIndex = $index + 1;

                    while ($nextIndex < $lineCount && trim($lines[$nextIndex]) === '') {
                        $nextIndex++;
                    }

                    if ($nextIndex < $lineCount && $this->isMarkdownTableRow($lines[$nextIndex])) {
                        $index = $nextIndex;

                        continue;
                    }

                    break;
                }

                if (! $this->isMarkdownTableRow($current)) {
                    break;
                }

                $result[] = trim($current);
                $index++;
            }
        }

        return implode("\n", $result);
    }

    private function stripBom(string $content): string
    {
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            return substr($content, 3);
        }

        return $content;
    }

    private function isMarkdownTableRow(string $line): bool
    {
        $trimmed = trim($line);

        return $trimmed !== ''
            && str_starts_with($trimmed, '|')
            && str_contains(substr($trimmed, 1), '|');
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

    /**
     * @return array{0: string, 1: string}
     */
    private function extractQuizBlock(string $content): array
    {
        $pattern = '/\n?'.preg_quote(SalesBookQuizParser::START_MARKER, '/').'.*?'.preg_quote(SalesBookQuizParser::END_MARKER, '/').'/s';

        if (preg_match($pattern, $content, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            return [$content, ''];
        }

        $block = $matches[0][0];
        $body = str_replace($block, '', $content);

        return [$body, $block];
    }
}
