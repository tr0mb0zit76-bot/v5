<?php

namespace App\Contracts\Inference;

interface ChatCompletionClient
{
    /**
     * Готов ли провайдер принимать запросы (например, задан ключ API).
     */
    public function isAvailable(): bool;

    /**
     * OpenAI-совместимый диалог (system/user/assistant).
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array{model?: string, temperature?: float, max_tokens?: int}  $parameters
     *
     * @throws \Throwable при сетевых/HTTP ошибках или ответе API вне успешной схемы
     */
    public function chat(array $messages, array $parameters = []): string;
}
