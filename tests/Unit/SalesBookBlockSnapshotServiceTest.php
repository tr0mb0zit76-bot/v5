<?php

namespace Tests\Unit;

use App\Services\SalesBook\SalesBookBlockSnapshotService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesBookBlockSnapshotServiceTest extends TestCase
{
    private SalesBookBlockSnapshotService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SalesBookBlockSnapshotService::class);
    }

    #[Test]
    public function it_builds_structured_blocks_from_markdown(): void
    {
        $snapshot = $this->service->fromStoredMarkdown(<<<'MD'
# КП для клиента

Короткое объяснение.

- первый пункт
- второй пункт

| Колонка | Значение |
| --- | --- |
| Этап | КП |

```mermaid
flowchart LR
    A --> B
```
MD);

        $this->assertSame(SalesBookBlockSnapshotService::SCHEMA, $snapshot['schema']);
        $this->assertSame('markdown', $snapshot['source_format']);
        $this->assertSame(['heading', 'paragraph', 'list', 'table', 'code'], array_column($snapshot['blocks'], 'type'));
        $this->assertSame('КП для клиента', $snapshot['blocks'][0]['text']);
        $this->assertSame(1, $snapshot['blocks'][0]['level']);
        $this->assertSame('mermaid', $snapshot['blocks'][4]['language']);
        $this->assertGreaterThan(0, $snapshot['stats']['word_count']);
    }

    #[Test]
    public function it_includes_quiz_block_when_article_has_quiz_marker(): void
    {
        $snapshot = $this->service->fromStoredMarkdown(<<<'MD'
# Проверка

Текст статьи.

<!-- sales-book:quiz -->
{
  "questions": [
    {
      "text": "Что отправить клиенту?",
      "options": [
        {"id": "a", "text": "КП"},
        {"id": "b", "text": "Пустое письмо"}
      ],
      "correct": "a"
    }
  ]
}
<!-- /sales-book:quiz -->
MD);

        $this->assertSame('quiz', $snapshot['blocks'][2]['type']);
        $this->assertSame(1, $snapshot['blocks'][2]['question_count']);
    }

    #[Test]
    public function it_exports_supported_blocks_to_markdown(): void
    {
        $markdown = $this->service->markdownFromBlocks([
            ['type' => 'heading', 'level' => 2, 'text' => 'Возражение по цене'],
            ['type' => 'paragraph', 'text' => 'Сначала уточняем маршрут.'],
            ['type' => 'todo_list', 'items' => [
                ['text' => 'Проверить ставку', 'checked' => true],
                ['text' => 'Отправить КП', 'checked' => false],
            ]],
        ]);

        $this->assertSame(<<<'MD'
## Возражение по цене

Сначала уточняем маршрут.

- [x] Проверить ставку
- [ ] Отправить КП
MD, $markdown);
    }

    #[Test]
    public function it_parses_article_collection_directive(): void
    {
        $snapshot = $this->service->fromStoredMarkdown(<<<'MD'
# Навигатор

```sales-book-view
{
  "title": "КП для менеджера",
  "view_slug": "manager-materials",
  "filters": {"sales_stage": "offer"},
  "limit": 5
}
```
MD);

        $this->assertSame('article_collection', $snapshot['blocks'][1]['type']);
        $this->assertSame('КП для менеджера', $snapshot['blocks'][1]['title']);
        $this->assertSame('manager-materials', $snapshot['blocks'][1]['view_slug']);
        $this->assertSame('offer', $snapshot['blocks'][1]['filters']['sales_stage']);
    }

    #[Test]
    public function it_exports_article_collection_blocks_to_directive(): void
    {
        $markdown = $this->service->markdownFromBlocks([
            [
                'type' => 'article_collection',
                'title' => 'Материалы для новичка',
                'view_slug' => 'table',
                'filters' => ['audience_role' => 'newcomer'],
                'limit' => 6,
            ],
        ]);

        $this->assertStringContainsString('```sales-book-view', $markdown);
        $this->assertStringContainsString('"title": "Материалы для новичка"', $markdown);
        $this->assertStringContainsString('"audience_role": "newcomer"', $markdown);
    }
}
