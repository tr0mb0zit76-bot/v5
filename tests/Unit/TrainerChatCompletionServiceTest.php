<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\SalesScript;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptVersion;
use App\Services\SalesScripts\TrainerChatCompletionService;
use Tests\TestCase;

class TrainerChatCompletionServiceTest extends TestCase
{
    public function test_hard_price_profile_adds_negotiation_rules_to_system_prompt(): void
    {
        $client = new class implements ChatCompletionClient
        {
            /** @var list<array{role: string, content: string}> */
            public array $messages = [];

            public function isAvailable(): bool
            {
                return true;
            }

            public function chat(array $messages, array $parameters = []): string
            {
                $this->messages = $messages;

                return 'У конкурента дешевле. Что вы можете предложить по цене?';
            }
        };

        $this->app->instance(ChatCompletionClient::class, $client);

        $script = SalesScript::query()->create([
            'title' => 'Тренажёр: цена и конкурент',
            'description' => 'Тестовый сценарий',
            'channel' => 'phone',
            'tags' => ['тренажёр', 'цена'],
        ]);
        $version = SalesScriptVersion::query()->create([
            'sales_script_id' => $script->id,
            'version_number' => 1,
            'published_at' => now(),
            'is_active' => true,
            'entry_node_key' => 'intro',
        ]);
        $session = SalesScriptPlaySession::query()->create([
            'sales_script_version_id' => $version->id,
            'is_trainer' => true,
            'trainer_profile_key' => 'hard-price-negotiator',
            'trainer_profile_title' => 'Жёсткий переговорщик по цене',
            'trainer_profile_context' => 'Давит на цену и конкурента.',
            'training_role_mode' => 'manager_seller',
            'started_at' => now(),
        ]);

        $service = $this->app->make(TrainerChatCompletionService::class);

        $service->replyForTrainerSession(
            $session,
            [
                'key' => 'hard-price-negotiator',
                'title' => 'Жёсткий переговорщик по цене',
                'context' => 'Давит на цену и конкурента.',
            ],
            [],
            'Добрый день, давайте обсудим ставку.',
            null,
            [],
        );

        $systemPrompt = (string) ($client->messages[0]['content'] ?? '');

        $this->assertStringContainsString('Сразу дави на цену и конкурента', $systemPrompt);
        $this->assertStringContainsString('обмен уступки на объём', $systemPrompt);
        $this->assertStringContainsString('предоплату', $systemPrompt);
        $this->assertStringContainsString('SLA', $systemPrompt);
    }
}
