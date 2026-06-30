<?php

namespace App\Services\SalesScripts;

use App\Models\SalesScriptNode;
use App\Models\SalesScriptTransition;

/**
 * Сопоставляет реплику ИИ-клиента с исходящим переходом по реакции в графе сценария.
 */
final class TrainerClientReactionMatcher
{
    public function __construct(
        private readonly SalesScriptPlaySessionService $playSessionService,
    ) {}

    /**
     * @return array{reaction_class_id: int, transition_id: int}|null
     */
    public function match(SalesScriptNode $node, string $clientReply): ?array
    {
        $reply = mb_strtolower(trim($clientReply), 'UTF-8');
        if ($reply === '') {
            return null;
        }

        $outgoing = $this->playSessionService->outgoingTransitions($node);
        $reactionTransitions = array_values(array_filter(
            $outgoing,
            fn (SalesScriptTransition $t): bool => $t->sales_script_reaction_class_id !== null,
        ));

        if ($reactionTransitions === []) {
            return null;
        }

        $best = null;
        $bestScore = 0;

        foreach ($reactionTransitions as $transition) {
            $score = $this->scoreTransition($transition, $reply);
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $transition;
            }
        }

        if ($best === null || $bestScore < 2) {
            return null;
        }

        return [
            'reaction_class_id' => (int) $best->sales_script_reaction_class_id,
            'transition_id' => (int) $best->id,
        ];
    }

    private function scoreTransition(SalesScriptTransition $transition, string $reply): int
    {
        $score = 0;
        $customerLabel = mb_strtolower(trim((string) ($transition->customer_label ?? '')), 'UTF-8');
        $reactionLabel = mb_strtolower(trim((string) ($transition->reactionClass?->label ?? '')), 'UTF-8');
        $reactionKey = mb_strtolower(trim((string) ($transition->reactionClass?->key ?? '')), 'UTF-8');

        if ($customerLabel !== '') {
            if ($reply === $customerLabel) {
                $score += 12;
            } elseif (mb_strpos($reply, $customerLabel, 0, 'UTF-8') !== false || mb_strpos($customerLabel, $reply, 0, 'UTF-8') !== false) {
                $score += 8;
            } else {
                $score += $this->tokenOverlapScore($reply, $customerLabel) * 3;
            }
        }

        if ($reactionLabel !== '') {
            $score += $this->tokenOverlapScore($reply, $reactionLabel) * 2;
        }

        $score += $this->keywordScore($reply, $reactionKey);

        return $score;
    }

    private function keywordScore(string $reply, string $reactionKey): int
    {
        return match ($reactionKey) {
            'positive_signal' => $this->containsAny($reply, ['да', 'соглас', 'давайте', 'подходит', 'интерес', 'соедин', 'это я', 'готов']) ? 4 : 0,
            'price_objection' => $this->containsAny($reply, ['дорог', 'цена', 'ставк', 'дешевле', 'бюджет', 'переплат']) ? 5 : 0,
            'competitor' => $this->containsAny($reply, ['конкурент', 'другой подрядчик', 'уже работаем', 'текущий перевозчик', 'другая компания', 'перевозчик нас устраивает', 'перевозчик устраивает', 'нас устраивает', 'работает в штатном режиме', 'всё работает']) ? 5 : 0,
            'need_info' => $this->containsAny($reply, ['пришлите', 'на почту', 'документ', 'кп', 'расчёт', 'подробн', 'уточн', 'конкретное предложение', 'предложение']) ? 4 : 0,
            'stall' => $this->containsAny($reply, ['не сейчас', 'позже', 'занят', 'перезвон', 'неудобно', 'нет времени', 'посмотрю', 'вернусь']) ? 4 : 0,
            default => 0,
        };
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (mb_strpos($haystack, $needle, 0, 'UTF-8') !== false) {
                return true;
            }
        }

        return false;
    }

    private function tokenOverlapScore(string $a, string $b): int
    {
        $tokensA = $this->tokens($a);
        $tokensB = $this->tokens($b);
        if ($tokensA === [] || $tokensB === []) {
            return 0;
        }

        $overlap = count(array_intersect($tokensA, $tokensB));

        return min(4, $overlap);
    }

    /**
     * @return list<string>
     */
    private function tokens(string $text): array
    {
        $pieces = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text, 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY);
        if (! is_array($pieces)) {
            return [];
        }

        $out = [];
        foreach ($pieces as $piece) {
            $t = (string) $piece;
            if (mb_strlen($t, 'UTF-8') >= 3) {
                $out[] = $t;
            }
        }

        return array_values(array_unique($out));
    }
}
