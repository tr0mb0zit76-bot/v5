<?php

namespace App\Services;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\User;
use App\Services\Agents\AiRequestGate;
use App\Services\Inference\ExternalLlmPayloadSanitizer;
use App\Support\AiChannel;
use App\Support\LeadIntakeMapper;
use App\Support\OrderIntakeLlmContext;
use App\Support\OrderIntakePhraseNormalizer;
use App\Support\OrderIntakeSchema;
use App\Support\TransportIntakeHeuristicParser;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransportTextIntakeService
{
    public function __construct(
        private readonly ChatCompletionClient $chat,
        private readonly ExternalLlmPayloadSanitizer $sanitizer,
        private readonly AiRequestGate $aiGate,
    ) {}

    /**
     * @return array{
     *     extracted: array<string, mixed>,
     *     parser: string,
     *     warnings: list<string>,
     *     confidence: float|null
     * }
     */
    public function extract(User $user, string $message): array
    {
        $text = trim($message);
        $maxChars = max(500, (int) config('ai.order_intake.max_text_chars', 12000));
        $warnings = [];

        if (mb_strlen($text) > $maxChars) {
            $text = mb_substr($text, 0, $maxChars);
            $warnings[] = 'Текст заявки обрезан до '.$maxChars.' символов.';
        }

        if ($this->canUseLlm($user)) {
            try {
                $extracted = $this->structureWithLlm($user, $text);
                $extracted = OrderIntakePhraseNormalizer::normalizeExtracted($extracted, $user);

                return [
                    'extracted' => $extracted,
                    'parser' => 'llm',
                    'warnings' => $warnings,
                    'confidence' => isset($extracted['confidence']) ? (float) $extracted['confidence'] : null,
                ];
            } catch (Throwable $throwable) {
                Log::warning('transport_intake_llm_failed', [
                    'user_id' => $user->id,
                    'message' => $throwable->getMessage(),
                ]);

                $warnings[] = 'AI-распознавание недоступно, использованы эвристики.';
            }
        }

        $heuristic = TransportIntakeHeuristicParser::parse($text);

        return [
            'extracted' => LeadIntakeMapper::heuristicToExtractedShape($heuristic),
            'parser' => 'heuristic',
            'warnings' => $warnings,
            'confidence' => null,
        ];
    }

    /**
     * Shared LLM extraction for orders and leads (OrderIntakeSchema JSON).
     *
     * @return array<string, mixed>
     */
    public function structureWithLlm(User $user, string $text): array
    {
        if (! $this->canUseLlm($user)) {
            throw new \RuntimeException('LLM intake is not available for this user.');
        }

        $messages = [
            ['role' => 'system', 'content' => OrderIntakeSchema::llmSystemPrompt()],
            ['role' => 'user', 'content' => OrderIntakeLlmContext::wrapUserInstruction($user, $text)],
        ];

        $messages = $this->sanitizer->sanitizeMessages($messages, 'command_bar');

        $content = $this->chat->chat($messages, [
            'temperature' => (float) config('ai.order_intake.temperature', 0.1),
            'max_tokens' => (int) config('ai.order_intake.max_tokens', 2500),
        ]);

        return OrderIntakeSchema::parseLlmJson($content);
    }

    private function canUseLlm(User $user): bool
    {
        if (! (bool) config('ai.order_intake.enabled', true)) {
            return false;
        }

        return $this->aiGate->channelFor('order_intake', $user) !== AiChannel::LocalOnly;
    }
}
