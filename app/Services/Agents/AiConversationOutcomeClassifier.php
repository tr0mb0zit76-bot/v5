<?php

namespace App\Services\Agents;

use App\Support\AiInteractionOutcome;

class AiConversationOutcomeClassifier
{
    /**
     * @param  list<string>  $toolsUsed
     */
    public function classify(
        string $reply,
        bool $hadException,
        bool $channelUnavailable,
        int $toolRounds,
        array $toolsUsed = [],
    ): AiInteractionOutcome {
        if ($channelUnavailable) {
            return AiInteractionOutcome::Unavailable;
        }

        if ($hadException) {
            return AiInteractionOutcome::Failed;
        }

        $trimmed = trim($reply);

        if ($trimmed === '') {
            return AiInteractionOutcome::WeakAnswer;
        }

        $lower = mb_strtolower($trimmed);

        foreach ($this->weakAnswerPhrases() as $phrase) {
            if (str_contains($lower, $phrase)) {
                return AiInteractionOutcome::WeakAnswer;
            }
        }

        if ($toolRounds === 0 && mb_strlen($trimmed) < 40) {
            return AiInteractionOutcome::WeakAnswer;
        }

        return AiInteractionOutcome::Success;
    }

    /**
     * @return list<string>
     */
    private function weakAnswerPhrases(): array
    {
        return [
            'не удалось сформировать ответ',
            'не удалось получить ответ ассистента',
            'повторите запрос',
            'уточните вопрос',
            'запрос слишком сложный',
            'введите вопрос',
            'недоступен',
            'deepseek_api_key',
            'пока не могу',
            'не могу этого делать',
            'вам это недоступно',
            'не поддерживается',
        ];
    }
}
