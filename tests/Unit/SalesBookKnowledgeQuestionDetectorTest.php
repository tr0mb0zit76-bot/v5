<?php

namespace Tests\Unit;

use App\Services\Agents\SalesBookKnowledgeQuestionDetector;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesBookKnowledgeQuestionDetectorTest extends TestCase
{
    private SalesBookKnowledgeQuestionDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new SalesBookKnowledgeQuestionDetector;
    }

    #[Test]
    public function it_detects_cmr_questions(): void
    {
        $this->assertTrue($this->detector->isLikely('что ты знаешь про CMR?'));
    }

    #[Test]
    public function it_detects_follow_up_after_book_mention(): void
    {
        $history = [
            [
                'role' => 'assistant',
                'content' => 'В Книге продаж есть статья «День 6: Документация от CMR до инвойса».',
            ],
        ];

        $this->assertTrue($this->detector->isLikely('какие поля заполнять', $history));
    }

    #[Test]
    public function it_ignores_operational_order_questions(): void
    {
        $this->assertFalse($this->detector->isLikely('найди заказ 1042'));
    }
}
