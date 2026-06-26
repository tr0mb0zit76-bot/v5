<?php

namespace Tests\Unit;

use App\Support\SalesBookContentNormalizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesBookContentNormalizerTest extends TestCase
{
    private SalesBookContentNormalizer $normalizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->normalizer = app(SalesBookContentNormalizer::class);
    }

    #[Test]
    public function it_converts_html_body_to_markdown(): void
    {
        $result = $this->normalizer->normalize('<p><strong>Заголовок</strong></p><ul><li>Пункт</li></ul>');

        $this->assertStringContainsString('**Заголовок**', $result);
        $this->assertStringContainsString('Пункт', $result);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<strong>', $result);
    }

    #[Test]
    public function it_preserves_child_links_block_while_normalizing_body(): void
    {
        $content = "<p>Текст</p>\n\n<!-- sales-book:child-links -->\n- [Раздел](/book?article_id=2)\n<!-- /sales-book:child-links -->";

        $result = $this->normalizer->normalize($content);

        $this->assertStringContainsString('Текст', $result);
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringContainsString('<!-- sales-book:child-links -->', $result);
        $this->assertStringContainsString('[Раздел](/book?article_id=2)', $result);
    }

    #[Test]
    public function for_editor_strips_child_links_block(): void
    {
        $content = "# Раздел\n\n<!-- sales-book:child-links -->\n- [Дочерняя](/x)\n<!-- /sales-book:child-links -->";

        $this->assertSame('# Раздел', $this->normalizer->forEditor($content));
    }

    #[Test]
    public function for_reader_includes_child_links_as_plain_markdown_list(): void
    {
        $content = "# Раздел\n\n<!-- sales-book:child-links -->\n- [День 4](/sales-assistant/book?article_id=4)\n- [День 5](/sales-assistant/book?article_id=5)\n<!-- /sales-book:child-links -->";

        $result = $this->normalizer->forReader($content);

        $this->assertStringContainsString('# Раздел', $result);
        $this->assertStringContainsString('- [День 4](/sales-assistant/book?article_id=4)', $result);
        $this->assertStringContainsString('- [День 5](/sales-assistant/book?article_id=5)', $result);
        $this->assertStringNotContainsString('<!-- sales-book:child-links -->', $result);
    }

    #[Test]
    public function for_reader_returns_only_child_links_when_body_is_empty(): void
    {
        $content = "<!-- sales-book:child-links -->\n- [Инструкция](/sales-assistant/book?article_id=1)\n<!-- /sales-book:child-links -->";

        $this->assertSame(
            '- [Инструкция](/sales-assistant/book?article_id=1)',
            $this->normalizer->forReader($content),
        );
    }

    #[Test]
    public function plain_markdown_is_left_unchanged(): void
    {
        $markdown = "## Заголовок\n\n- пункт один\n- пункт два";

        $this->assertSame($markdown, $this->normalizer->normalize($markdown));
    }

    #[Test]
    public function it_collapses_blank_lines_inside_markdown_tables(): void
    {
        $broken = <<<'MD'
## Таблица

| Ситуация | Что делать |

| --- | --- |

| **Лимит** | Вкладка **Груз** |
MD;

        $expected = <<<'MD'
## Таблица

| Ситуация | Что делать |
| --- | --- |
| **Лимит** | Вкладка **Груз** |
MD;

        $this->assertSame($expected, $this->normalizer->normalize($broken));
        $this->assertSame($expected, $this->normalizer->forEditor($broken));
    }

    #[Test]
    public function it_trims_indented_markdown_table_rows(): void
    {
        $broken = <<<'MD'
## Таблица

    | Колонка A | Колонка B |
    | --- | --- |
    | 100 | 200 |
MD;

        $expected = <<<'MD'
## Таблица

| Колонка A | Колонка B |
| --- | --- |
| 100 | 200 |
MD;

        $this->assertSame($expected, $this->normalizer->normalize($broken));
    }

    #[Test]
    public function it_replaces_local_marktext_image_paths_with_editor_hint(): void
    {
        $markdown = <<<'MD'
## Скриншот

![](C:\Users\Example\AppData\Roaming\marktext\images\sample.png)
MD;

        $result = $this->normalizer->forEditor($markdown);

        $this->assertStringNotContainsString('marktext', $result);
        $this->assertStringNotContainsString('![](C:', $result);
        $this->assertStringContainsString('загрузите изображение через кнопку «Картинка»', $result);
    }

    #[Test]
    public function it_keeps_http_and_sales_book_asset_images(): void
    {
        $markdown = <<<'MD'
![logo](https://example.com/logo.png)

![upload](/sales-assistant/book/assets?path=sales-book-assets%2Fdemo.png)
MD;

        $result = $this->normalizer->forEditor($markdown);

        $this->assertStringContainsString('![logo](https://example.com/logo.png)', $result);
        $this->assertStringContainsString('![upload](/sales-assistant/book/assets?path=sales-book-assets%2Fdemo.png)', $result);
    }

    #[Test]
    public function it_preserves_mermaid_fenced_code_blocks(): void
    {
        $markdown = <<<'MD'
## Схема

```mermaid
flowchart LR
    A[Создание лида] --> B[Квалификация]
    B --> C[КП]
```
MD;

        $this->assertSame($markdown, $this->normalizer->normalize($markdown));
        $this->assertSame($markdown, $this->normalizer->forEditor($markdown));
        $this->assertSame($markdown, $this->normalizer->forReader($markdown));
    }
}
