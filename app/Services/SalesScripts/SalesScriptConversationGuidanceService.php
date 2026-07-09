<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesPlayEventType;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTransition;
use Illuminate\Support\Str;

class SalesScriptConversationGuidanceService
{
    public const EFFECT_POSITIVE = 'positive';

    public const EFFECT_NEUTRAL = 'neutral';

    public const EFFECT_RISK = 'risk';

    public const EFFECT_CRITICAL = 'critical';

    /**
     * @return list<string>
     */
    public static function effects(): array
    {
        return [
            self::EFFECT_POSITIVE,
            self::EFFECT_NEUTRAL,
            self::EFFECT_RISK,
            self::EFFECT_CRITICAL,
        ];
    }

    /**
     * @return array{effect: string, effect_label: string, momentum_delta: int, next_move_preview: string|null, next_phase: string|null}
     */
    public function guidanceForTransition(SalesScriptTransition $transition): array
    {
        $effect = $this->effectForTransition($transition);
        $delta = $transition->momentum_delta !== null
            ? max(-2, min(2, (int) $transition->momentum_delta))
            : $this->defaultDelta($effect);

        return [
            'effect' => $effect,
            'effect_label' => $this->effectLabel($effect),
            'momentum_delta' => $delta,
            'next_move_preview' => $this->nextMovePreview($transition),
            'next_phase' => $this->phaseForNode($transition->toNode),
        ];
    }

    /**
     * @return array{
     *     score: int,
     *     level: string,
     *     label: string,
     *     phase: string|null,
     *     last_delta: int,
     *     last_effect: string,
     *     movement_label: string
     * }
     */
    public function stateForSession(SalesScriptPlaySession $session, ?SalesScriptNode $current): array
    {
        $score = 50;
        $lastDelta = 0;
        $lastEffect = self::EFFECT_NEUTRAL;

        foreach ($session->events as $event) {
            if ($event->type !== SalesPlayEventType::RecordedReaction) {
                continue;
            }

            $meta = is_array($event->meta) ? $event->meta : [];
            $effect = $this->normalizeEffect($meta['conversation_effect'] ?? null)
                ?? $this->effectForReactionKey($event->reactionClass?->key);
            $delta = isset($meta['momentum_delta'])
                ? max(-2, min(2, (int) $meta['momentum_delta']))
                : $this->defaultDelta($effect);

            $score += $delta * 12;
            $lastDelta = $delta;
            $lastEffect = $effect;
        }

        $score = max(0, min(100, $score));
        $level = match (true) {
            $score >= 80 => 'agreement',
            $score >= 60 => 'interest',
            $score >= 40 => 'open',
            $score >= 20 => 'resistance',
            default => 'exit_risk',
        };

        return [
            'score' => $score,
            'level' => $level,
            'label' => match ($level) {
                'agreement' => 'Договорённость близко',
                'interest' => 'Есть интерес',
                'open' => 'Диалог открыт',
                'resistance' => 'Сопротивление',
                default => 'Риск ухода',
            },
            'phase' => $this->phaseForNode($current),
            'last_delta' => $lastDelta,
            'last_effect' => $lastEffect,
            'movement_label' => match (true) {
                $lastDelta >= 2 => 'Разговор заметно теплее',
                $lastDelta === 1 => 'Позиция улучшилась',
                $lastDelta === -1 => 'Появилось сопротивление',
                $lastDelta <= -2 => 'Высокий риск потерять диалог',
                default => 'Позиция не изменилась',
            },
        ];
    }

    public function effectForTransition(SalesScriptTransition $transition): string
    {
        return $this->normalizeEffect($transition->conversation_effect)
            ?? $this->effectForReactionKey($transition->reactionClass?->key);
    }

    public function effectLabel(string $effect): string
    {
        return match ($effect) {
            self::EFFECT_POSITIVE => 'Интерес растёт',
            self::EFFECT_RISK => 'Есть риск',
            self::EFFECT_CRITICAL => 'Критичный поворот',
            default => 'Нейтрально',
        };
    }

    private function effectForReactionKey(?string $reactionKey): string
    {
        return match ($reactionKey) {
            'positive_signal' => self::EFFECT_POSITIVE,
            'price_objection', 'competitor', 'no_need_objection' => self::EFFECT_RISK,
            'stall' => self::EFFECT_CRITICAL,
            default => self::EFFECT_NEUTRAL,
        };
    }

    private function defaultDelta(string $effect): int
    {
        return match ($effect) {
            self::EFFECT_POSITIVE => 1,
            self::EFFECT_RISK => -1,
            self::EFFECT_CRITICAL => -2,
            default => 0,
        };
    }

    private function normalizeEffect(mixed $effect): ?string
    {
        return is_string($effect) && in_array($effect, self::effects(), true)
            ? $effect
            : null;
    }

    private function nextMovePreview(SalesScriptTransition $transition): ?string
    {
        $explicit = trim((string) $transition->next_move_preview);
        if ($explicit !== '') {
            return Str::limit($explicit, 220);
        }

        $body = trim((string) $transition->toNode?->body);
        if ($body === '') {
            return null;
        }

        if (preg_match('/«([^»]{12,300})»/u', $body, $matches) === 1) {
            return '«'.Str::limit(trim($matches[1]), 180).'»';
        }

        return null;
    }

    private function phaseForNode(?SalesScriptNode $node): ?string
    {
        $tags = array_map(
            fn (mixed $tag): string => mb_strtolower(trim((string) $tag)),
            (array) ($node?->tags ?? []),
        );

        $catalog = [
            'старт' => 'Установление контакта',
            'рамка' => 'Установление контакта',
            'контакт' => 'Установление контакта',
            'лпр' => 'Выход на ЛПР',
            'квалификация' => 'Квалификация',
            'спин' => 'Диагностика',
            'диагностика' => 'Диагностика',
            'возражение' => 'Работа с возражением',
            'цена' => 'Переговоры о цене',
            'ценность' => 'Обоснование ценности',
            'кп' => 'Предложение',
            'следующий шаг' => 'Фиксация следующего шага',
            'итог' => 'Фиксация договорённости',
            'завершение' => 'Завершение',
        ];

        foreach ($catalog as $tag => $label) {
            if (in_array($tag, $tags, true)) {
                return $label;
            }
        }

        return null;
    }
}
