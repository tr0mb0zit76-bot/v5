<?php

namespace Tests\Unit\Services\Improvement;

use App\Contracts\Inference\ChatCompletionClient;
use App\Services\Improvement\ImprovementHypothesisLlmClient;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ImprovementHypothesisLlmClientTest extends TestCase
{
    #[Test]
    public function generate_ideas_recovers_complete_objects_from_truncated_json(): void
    {
        $truncated = '{"ideas":['
            .'{"category":"script","text":"Скрипт сегмент-аналитик","short_reason":"Ниша"},'
            .'{"category":"channel","text":"Мини-кейсы по вертикалям","short_reason":"Экспертиза"},'
            .'{"category":"price","text":"Оффер расчёт за 2 часа","short_reason":"Скорость"},'
            .'{"category":"script","text":"Три вопроса о боли","short_reason":"Потребность"},'
            .'{"category":"script","text":"Возврат ушедших: сравнить условия конкурента и предложить монитор';

        $chat = new class($truncated) implements ChatCompletionClient
        {
            public function __construct(private string $payload) {}

            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $parameters = []): string
            {
                return $this->payload;
            }
        };

        $client = new ImprovementHypothesisLlmClient($chat, app(ExternalLlmPayloadSanitizer::class));
        $ideas = $client->generateIdeas(['Не наш сегмент', 'Нет потребности']);

        $this->assertCount(4, $ideas);
        $this->assertSame('script', $ideas[0]['category']);
        $this->assertSame('Скрипт сегмент-аналитик', $ideas[0]['text']);
        $this->assertSame('Оффер расчёт за 2 часа', $ideas[2]['text']);
    }
}
