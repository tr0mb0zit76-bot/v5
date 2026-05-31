<?php

namespace App\Contracts\Inference;

interface ToolAwareChatCompletionClient extends ChatCompletionClient
{
    /**
     * OpenAI-совместимый chat/completions с tools.
     *
     * @param  list<array{role: string, content?: string|null, tool_calls?: list<array<string, mixed>>, tool_call_id?: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array{model?: string, temperature?: float, max_tokens?: int}  $parameters
     * @return array{
     *     message: array{role: string, content: ?string, tool_calls?: list<array<string, mixed>>},
     *     usage?: array<string, mixed>
     * }
     */
    public function chatWithTools(array $messages, array $tools, array $parameters = []): array;
}
