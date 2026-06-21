<?php

namespace Tests\Unit\Support;

use App\Support\SalesBookContentNormalizer;
use App\Support\SalesBookQuizParser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesBookQuizParserTest extends TestCase
{
    #[Test]
    public function it_parses_valid_quiz_block(): void
    {
        $content = <<<'MD'
# Intro

<!-- sales-book:quiz -->
{"questions":[{"id":"q1","text":"Question?","options":[{"id":"a","text":"A"},{"id":"b","text":"B"}],"correct":"b","explanation":"Because B"}]}
<!-- /sales-book:quiz -->
MD;

        $quiz = (new SalesBookQuizParser)->parse($content);

        $this->assertNotNull($quiz);
        $this->assertCount(1, $quiz['questions']);
        $this->assertSame('q1', $quiz['questions'][0]['id']);
        $this->assertSame('b', $quiz['questions'][0]['correct']);
        $this->assertSame('Because B', $quiz['questions'][0]['explanation']);
    }

    #[Test]
    public function it_strips_quiz_block_from_content(): void
    {
        $content = "# Intro\n\n<!-- sales-book:quiz -->\n{}\n<!-- /sales-book:quiz -->";

        $stripped = (new SalesBookQuizParser)->stripBlock($content);

        $this->assertSame('# Intro', $stripped);
    }

    #[Test]
    public function normalizer_hides_quiz_in_editor_and_reader(): void
    {
        $normalizer = new SalesBookContentNormalizer(new SalesBookQuizParser);

        $content = <<<'MD'
# Page

Intro text.

<!-- sales-book:quiz -->
{"questions":[{"id":"q1","text":"Question?","options":[{"id":"a","text":"A"},{"id":"b","text":"B"}],"correct":"a"}]}
<!-- /sales-book:quiz -->
MD;

        $this->assertStringNotContainsString('sales-book:quiz', $normalizer->forEditor($content));
        $this->assertStringNotContainsString('sales-book:quiz', $normalizer->forReader($content));
        $this->assertStringContainsString('Intro text.', $normalizer->forReader($content));
        $this->assertNotNull($normalizer->parseQuiz($content));
    }

    #[Test]
    public function normalizer_preserves_quiz_block_on_save_from_editor(): void
    {
        $normalizer = new SalesBookContentNormalizer(new SalesBookQuizParser);

        $existing = <<<'MD'
# Page

Intro.

<!-- sales-book:quiz -->
{"questions":[{"id":"q1","text":"Question?","options":[{"id":"a","text":"A"},{"id":"b","text":"B"}],"correct":"a"}]}
<!-- /sales-book:quiz -->
MD;

        $incoming = "# Page\n\nUpdated intro.";

        $merged = $normalizer->preserveQuizBlock($incoming, $existing);

        $this->assertStringContainsString('Updated intro.', $merged);
        $this->assertStringContainsString('<!-- sales-book:quiz -->', $merged);
        $this->assertNotNull($normalizer->parseQuiz($merged));
    }
}
