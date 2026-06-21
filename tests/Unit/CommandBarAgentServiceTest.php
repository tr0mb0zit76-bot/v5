<?php

namespace Tests\Unit;

use App\Contracts\Inference\ChatCompletionClient;
use App\Contracts\Inference\ToolAwareChatCompletionClient;
use App\Models\User;
use App\Services\Agents\AgentToolRegistry;
use App\Services\Agents\AiConversationOutcomeClassifier;
use App\Services\Agents\AiRequestGate;
use App\Services\Agents\CommandBarAgentService;
use App\Services\Agents\CommandBarAttachmentService;
use App\Services\Agents\SalesBookKnowledgeQuestionDetector;
use App\Services\Agents\SalesBookTurnAnalyzer;
use App\Services\Ai\AiInteractionRecorder;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use App\Services\Mcp\AiToolAuditLogger;
use App\Services\SalesBook\SalesBookArticleFeedbackRecorder;
use Tests\TestCase;

class CommandBarAgentServiceTest extends TestCase
{
    public function test_returns_stub_when_external_channel_unavailable(): void
    {
        $user = new User(['id' => 1, 'name' => 'Test']);

        $chat = new class implements ChatCompletionClient, ToolAwareChatCompletionClient
        {
            public function isAvailable(): bool
            {
                return false;
            }

            public function chat(array $messages, array $parameters = []): string
            {
                return '';
            }

            public function chatWithTools(array $messages, array $tools, array $parameters = []): array
            {
                return ['message' => ['role' => 'assistant', 'content' => '']];
            }
        };

        $service = new CommandBarAgentService(
            new AiRequestGate($chat),
            $this->createStub(AgentToolRegistry::class),
            $chat,
            $this->createStub(AiToolAuditLogger::class),
            new AiInteractionRecorder(new ExternalLlmPayloadSanitizer),
            new AiConversationOutcomeClassifier,
            new ExternalLlmPayloadSanitizer,
            new SalesBookKnowledgeQuestionDetector,
            new SalesBookTurnAnalyzer,
            new SalesBookArticleFeedbackRecorder,
            app(CommandBarAttachmentService::class),
        );

        $result = $service->chat($user, 'Найди заказ 100');

        $this->assertStringContainsString('DEEPSEEK_API_KEY', $result['reply']);
        $this->assertSame('local_only', $result['channel']);
        $this->assertSame(0, $result['tool_rounds']);
    }
}
