<?php

namespace Tests\Unit;

use App\Services\Agents\SalesBookTurnAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesBookTurnAnalyzerTest extends TestCase
{
    private SalesBookTurnAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = new SalesBookTurnAnalyzer;
    }

    #[Test]
    public function it_marks_gap_when_knowledge_question_was_not_searched(): void
    {
        $result = $this->analyzer->analyze([
            ['role' => 'assistant', 'content' => 'Ответ без инструментов'],
        ], true);

        $this->assertTrue($result['gap']);
        $this->assertSame('not_searched', $result['gap_reason']);
    }

    #[Test]
    public function it_marks_gap_when_search_returned_empty(): void
    {
        $result = $this->analyzer->analyze([
            [
                'role' => 'tool',
                'content' => json_encode(['articles' => []], JSON_UNESCAPED_UNICODE),
            ],
        ], true);

        $this->assertTrue($result['gap']);
        $this->assertSame('no_results', $result['gap_reason']);
    }

    #[Test]
    public function it_is_ok_when_article_was_read(): void
    {
        $result = $this->analyzer->analyze([
            [
                'role' => 'tool',
                'content' => json_encode(['articles' => [['id' => 1]]], JSON_UNESCAPED_UNICODE),
            ],
            [
                'role' => 'tool',
                'content' => json_encode(['article' => ['id' => 1, 'title' => 'CMR']], JSON_UNESCAPED_UNICODE),
            ],
        ], true);

        $this->assertFalse($result['gap']);
        $this->assertSame([1], $result['article_ids_read']);
    }
}
