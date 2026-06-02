<?php

namespace App\Services\SalesScripts;

use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTrainerMessage;
use Illuminate\Support\Collection;

/**
 * Коучинговые подсказки для тренажёра: что делать, если диалог застрял.
 */
final class TrainerCoachingHintService
{
    public function __construct(
        private readonly TrainerDialogLoopDetector $loopDetector,
        private readonly TrainerDialogHintService $dialogHintService,
    ) {}

    /**
     * @param  Collection<int, SalesScriptTrainerMessage>  $messages
     * @return array{
     *     loop: array<string, mixed>,
     *     coaching_hint: string|null,
     *     suggested_focus: array<string, mixed>|null
     * }|null
     */
    public function build(
        SalesScriptPlaySession $session,
        Collection $messages,
        ?int $currentNodeId,
    ): ?array {
        if ($messages->isEmpty()) {
            return null;
        }

        $loop = $this->loopDetector->analyze($messages);

        if (! $loop['loop_detected']) {
            return null;
        }

        $hints = $this->dialogHintService->contextualNodeHints(
            (int) $session->sales_script_version_id,
            $currentNodeId,
            $messages,
            3,
        );

        $suggestedFocus = $hints[0] ?? null;
        $coachingHint = $this->composeHint($loop, $suggestedFocus, $session);

        return [
            'loop' => $loop,
            'coaching_hint' => $coachingHint,
            'suggested_focus' => $suggestedFocus,
        ];
    }

    /**
     * @param  array<string, mixed>  $loop
     * @param  array<string, mixed>|null  $suggestedFocus
     */
    private function composeHint(array $loop, ?array $suggestedFocus, SalesScriptPlaySession $session): string
    {
        $parts = ['Похоже, диалог зациклился.'];

        if (in_array('assistant_repeated_reply', $loop['reasons'] ?? [], true)) {
            $parts[] = 'Собеседник повторяет похожие реплики — смените угол: уточните потребность, предложите конкретный следующий шаг или мягко завершите тему.';
        }

        if (in_array('user_repeated_question', $loop['reasons'] ?? [], true)) {
            $parts[] = 'Вы повторяете один и тот же вопрос — попробуйте переформулировать или ответить на возражение иначе.';
        }

        if (in_array('assistant_quality_drop', $loop['reasons'] ?? [], true)) {
            $parts[] = 'Последние реплики AI выглядят слабыми — упростите формулировку и вернитесь к цели сценария.';
        }

        if (is_array($suggestedFocus) && ($suggestedFocus['hint'] ?? '') !== '') {
            $parts[] = 'Из сценария: '.trim((string) $suggestedFocus['hint']);
        } elseif (is_array($suggestedFocus) && ($suggestedFocus['excerpt'] ?? '') !== '') {
            $parts[] = 'Попробуйте развернуть разговор в сторону: «'.trim((string) $suggestedFocus['excerpt']).'»';
        }

        if ($session->training_role_mode === 'manager_seller') {
            $parts[] = 'Если нужна опора по продукту — откройте Книгу продаж по теме сценария.';
        }

        return implode(' ', $parts);
    }
}
