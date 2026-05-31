<?php

namespace App\Services\Agents;

use App\Contracts\Inference\ChatCompletionClient;
use App\Models\User;
use App\Support\AiChannel;

class AiRequestGate
{
    public function __construct(
        private readonly ChatCompletionClient $chatCompletionClient,
    ) {}

    public function channelFor(string $feature, User $user): AiChannel
    {
        if (! (bool) config('ai.command_bar.enabled', true)) {
            return AiChannel::LocalOnly;
        }

        if (in_array($feature, ['command_bar', 'order_intake'], true) && ! $this->chatCompletionClient->isAvailable()) {
            return AiChannel::LocalOnly;
        }

        return AiChannel::ExternalLarge;
    }

    public function unavailableMessage(string $feature): string
    {
        return match ($feature) {
            'command_bar' => 'ИИ-ассистент недоступен: задайте DEEPSEEK_API_KEY в .env или включите AI_COMMAND_BAR_ENABLED.',
            'order_intake' => 'Распознавание заявок недоступно: задайте DEEPSEEK_API_KEY в .env.',
            default => 'Внешний ИИ-канал недоступен для этой операции.',
        };
    }
}
