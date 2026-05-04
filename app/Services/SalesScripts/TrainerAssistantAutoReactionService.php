<?php

namespace App\Services\SalesScripts;

use App\Contracts\Inference\ChatCompletionClient;
use App\Enums\TrainerPeerReaction;
use App\Models\SalesScriptPlaySession;

/**
 * Автооценка реплики ассистента (тон / уместность для тренировки).
 * Сначала компактный запрос к LLM; при недоступности API или сбое — лексическая эвристика по русскому тексту.
 */
class TrainerAssistantAutoReactionService
{
    public function __construct(
        private readonly ChatCompletionClient $chatCompletionClient,
    ) {}

    public function classify(
        SalesScriptPlaySession $session,
        string $assistantReply,
        string $lastUserLine,
    ): TrainerPeerReaction {
        $trimReply = trim($assistantReply);
        if ($trimReply === '') {
            return TrainerPeerReaction::Neutral;
        }

        if ($this->chatCompletionClient->isAvailable()) {
            try {
                $parsed = $this->classifyViaLlm($session, $trimReply, trim($lastUserLine));
                if ($parsed !== null) {
                    return $parsed;
                }
            } catch (\Throwable) {
                // fallback ниже
            }
        }

        return $this->classifyHeuristic($trimReply);
    }

    private function classifyViaLlm(
        SalesScriptPlaySession $session,
        string $assistantReply,
        string $lastUserLine,
    ): ?TrainerPeerReaction {
        $mode = $session->training_role_mode === 'manager_buyer'
            ? 'Реплика от продавца (пользователь — покупатель).'
            : 'Реплика от клиента (пользователь — менеджер).';

        $system = "Ты оцениваешь ОДНУ реплику ассистента в учебном диалоге продаж. {$mode}\n".
            "Оцени, насколько реплика уместна, профессиональна и помогает диалогу.\n".
            "Ответь строго одним английским словом без знаков препинания:\n".
            "positive — если реплика в целом хорошая и по делу;\n".
            "neutral — если нейтральная или двусмысленная;\n".
            'negative — если резкая, токсичная, явно вредная или совсем не по теме продаж.';

        $user = "Последняя реплика пользователя:\n".
            ($lastUserLine !== '' ? $lastUserLine : '(нет текста)').
            "\n\nРеплика ассистента для оценки:\n".$assistantReply;

        $raw = $this->chatCompletionClient->chat(
            [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            [
                'temperature' => 0.15,
                'max_tokens' => 12,
            ],
        );

        return $this->parseLlmLabel($raw);
    }

    private function parseLlmLabel(string $raw): ?TrainerPeerReaction
    {
        $t = mb_strtolower(trim($raw), 'UTF-8');
        $t = preg_replace('/^[^\p{L}]+/u', '', $t) ?? $t;
        $first = explode(' ', $t, 2)[0] ?? $t;

        return match ($first) {
            'positive' => TrainerPeerReaction::Positive,
            'neutral' => TrainerPeerReaction::Neutral,
            'negative' => TrainerPeerReaction::Negative,
            default => null,
        };
    }

    private function classifyHeuristic(string $assistantReply): TrainerPeerReaction
    {
        $lower = mb_strtolower($assistantReply, 'UTF-8');

        $negativeNeedles = [
            'дурак', 'идиот', 'иди в жопу', 'отвали', 'заткнись', 'чушь', 'бред собачий',
            'не звоните', 'отстаньте', 'катись колбаской', 'полный бред', 'абсурд',
        ];
        foreach ($negativeNeedles as $n) {
            if (mb_strpos($lower, $n, 0, 'UTF-8') !== false) {
                return TrainerPeerReaction::Negative;
            }
        }

        $positiveNeedles = [
            'спасибо за', 'рад уточнить', 'давайте так', 'предлагаю обсудить',
            'готов предложить', 'можем согласовать', 'уточню детали', 'интересное предложение',
        ];
        foreach ($positiveNeedles as $p) {
            if (mb_strpos($lower, $p, 0, 'UTF-8') !== false) {
                return TrainerPeerReaction::Positive;
            }
        }

        return TrainerPeerReaction::Neutral;
    }
}
