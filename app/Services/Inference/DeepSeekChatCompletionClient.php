<?php

namespace App\Services\Inference;

use App\Contracts\Inference\ChatCompletionClient;
use App\Contracts\Inference\ToolAwareChatCompletionClient;
use Illuminate\Support\Facades\Http;

class DeepSeekChatCompletionClient implements ChatCompletionClient, ToolAwareChatCompletionClient
{
    /**
     * @param  positive-int  $timeoutSeconds
     */
    public function __construct(
        private readonly string $apiKey,
        private readonly string $completionsUrl,
        private readonly string $defaultModel,
        private readonly int $timeoutSeconds,
    ) {}

    public function isAvailable(): bool
    {
        return $this->apiKey !== '';
    }

    public function chat(array $messages, array $parameters = []): string
    {
        if (! $this->isAvailable()) {
            throw new \LogicException('DeepSeek: пустой ключ API.');
        }

        $model = (string) ($parameters['model'] ?? $this->defaultModel);

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];
        if (array_key_exists('temperature', $parameters)) {
            $payload['temperature'] = $parameters['temperature'];
        }
        if (array_key_exists('max_tokens', $parameters)) {
            $payload['max_tokens'] = $parameters['max_tokens'];
        }

        $response = Http::timeout(max(1, $this->timeoutSeconds))
            ->withToken($this->apiKey)
            ->asJson()
            ->post($this->completionsUrl, $payload)
            ->throw()
            ->json();

        return trim((string) data_get($response, 'choices.0.message.content', ''));
    }

    public function chatWithTools(array $messages, array $tools, array $parameters = []): array
    {
        if (! $this->isAvailable()) {
            throw new \LogicException('DeepSeek: пустой ключ API.');
        }

        $model = (string) ($parameters['model'] ?? $this->defaultModel);

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        if (array_key_exists('temperature', $parameters)) {
            $payload['temperature'] = $parameters['temperature'];
        }

        if (array_key_exists('max_tokens', $parameters)) {
            $payload['max_tokens'] = $parameters['max_tokens'];
        }

        $response = Http::timeout(max(1, $this->timeoutSeconds))
            ->withToken($this->apiKey)
            ->asJson()
            ->post($this->completionsUrl, $payload)
            ->throw()
            ->json();

        $message = data_get($response, 'choices.0.message', []);

        return [
            'message' => [
                'role' => (string) ($message['role'] ?? 'assistant'),
                'content' => array_key_exists('content', $message) ? $message['content'] : null,
                'tool_calls' => $message['tool_calls'] ?? null,
            ],
            'usage' => data_get($response, 'usage'),
        ];
    }
}
