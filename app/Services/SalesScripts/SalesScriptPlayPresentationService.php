<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesScriptNodeKind;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptTransition;

/**
 * Собирает представление шага прохождения: реплика оператора + варианты ответа клиента на одном экране.
 */
final class SalesScriptPlayPresentationService
{
    public function __construct(
        private readonly SalesScriptPlaySessionService $playSessionService,
    ) {}

    /**
     * @return array{
     *     operator_kind: string,
     *     operator_line: string|null,
     *     coaching_hint: string|null,
     *     branch_instruction: string|null,
     *     choices: list<array{
     *         transition_id: int,
     *         sales_script_reaction_class_id: int|null,
     *         label: string,
     *         subtitle: string|null,
     *         compound: bool
     *     }>,
     *     step_key: string|null,
     *     is_branch_only: bool
     * }
     */
    public function build(?SalesScriptNode $current): array
    {
        if ($current === null) {
            return $this->emptyPresentation();
        }

        $outgoing = $this->playSessionService->outgoingTransitions($current);
        $reactionTransitions = array_values(array_filter(
            $outgoing,
            fn (SalesScriptTransition $t): bool => $t->sales_script_reaction_class_id !== null,
        ));
        $linearTransitions = array_values(array_filter(
            $outgoing,
            fn (SalesScriptTransition $t): bool => $t->sales_script_reaction_class_id === null,
        ));

        $peek = $this->resolvePeekedChoices($linearTransitions, $reactionTransitions);

        if ($peek !== null) {
            return [
                'operator_kind' => $current->kind->value,
                'operator_line' => $this->operatorLine($current),
                'coaching_hint' => filled($current->hint) ? (string) $current->hint : null,
                'branch_instruction' => null,
                'choices' => $peek['choices'],
                'step_key' => $current->client_key,
                'is_branch_only' => false,
            ];
        }

        if ($current->kind === SalesScriptNodeKind::Branch && $reactionTransitions !== []) {
            return [
                'operator_kind' => $current->kind->value,
                'operator_line' => null,
                'coaching_hint' => filled($current->hint) ? (string) $current->hint : null,
                'branch_instruction' => trim((string) $current->body) !== '' ? trim((string) $current->body) : null,
                'choices' => $this->mapChoices($reactionTransitions, false),
                'step_key' => $current->client_key,
                'is_branch_only' => true,
            ];
        }

        $choices = [];
        if ($reactionTransitions !== []) {
            $choices = $this->mapChoices($reactionTransitions, false);
        } elseif (count($linearTransitions) === 1) {
            $choices = [$this->mapLinearChoice($linearTransitions[0])];
        }

        return [
            'operator_kind' => $current->kind->value,
            'operator_line' => $this->operatorLine($current),
            'coaching_hint' => filled($current->hint) ? (string) $current->hint : null,
            'branch_instruction' => null,
            'choices' => $choices,
            'step_key' => $current->client_key,
            'is_branch_only' => false,
        ];
    }

    /**
     * @param  list<SalesScriptTransition>  $linearTransitions
     * @param  list<SalesScriptTransition>  $reactionTransitions
     * @return array{choices: list<array<string, mixed>>}|null
     */
    private function resolvePeekedChoices(array $linearTransitions, array $reactionTransitions): ?array
    {
        if ($reactionTransitions !== [] || count($linearTransitions) !== 1) {
            return null;
        }

        $linear = $linearTransitions[0];
        $next = $linear->toNode;
        if ($next === null) {
            return null;
        }

        $nextOutgoing = $this->playSessionService->outgoingTransitions($next);
        $nextReactions = array_values(array_filter(
            $nextOutgoing,
            fn (SalesScriptTransition $t): bool => $t->sales_script_reaction_class_id !== null,
        ));

        if ($nextReactions === []) {
            return null;
        }

        if ($next->kind !== SalesScriptNodeKind::Branch && count($nextReactions) < 2) {
            return null;
        }

        return [
            'choices' => $this->mapChoices($nextReactions, true),
        ];
    }

    /**
     * @param  list<SalesScriptTransition>  $transitions
     * @return list<array<string, mixed>>
     */
    private function mapChoices(array $transitions, bool $compound): array
    {
        return array_map(function (SalesScriptTransition $transition) use ($compound): array {
            return [
                'transition_id' => (int) $transition->id,
                'sales_script_reaction_class_id' => $transition->sales_script_reaction_class_id,
                'label' => $this->choiceLabel($transition),
                'subtitle' => $this->choiceSubtitle($transition),
                'compound' => $compound,
            ];
        }, $transitions);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapLinearChoice(SalesScriptTransition $transition): array
    {
        $next = $transition->toNode;
        $preview = $next !== null ? $this->previewNextStep($next) : null;

        return [
            'transition_id' => (int) $transition->id,
            'sales_script_reaction_class_id' => null,
            'label' => $preview ?? 'Продолжить',
            'subtitle' => $preview !== null ? 'Следующий шаг сценария' : null,
            'compound' => false,
        ];
    }

    private function choiceLabel(SalesScriptTransition $transition): string
    {
        if (filled($transition->customer_label)) {
            return (string) $transition->customer_label;
        }

        return $transition->reactionClass?->label ?? 'Вариант ответа';
    }

    private function choiceSubtitle(SalesScriptTransition $transition): ?string
    {
        if (filled($transition->customer_label) && $transition->reactionClass !== null) {
            return $transition->reactionClass->label;
        }

        return null;
    }

    private function previewNextStep(SalesScriptNode $next): ?string
    {
        $nextOutgoing = $this->playSessionService->outgoingTransitions($next);
        $reactionCount = count(array_filter(
            $nextOutgoing,
            fn (SalesScriptTransition $t): bool => $t->sales_script_reaction_class_id !== null,
        ));

        if ($reactionCount >= 2) {
            return 'Выбрать реакцию клиента ('.$reactionCount.' варианта)';
        }

        if ($next->kind === SalesScriptNodeKind::Branch) {
            return 'Развилка: реакция клиента';
        }

        $line = $this->operatorLine($next);
        if ($line === null) {
            return null;
        }

        $excerpt = mb_strlen($line, 'UTF-8') > 72
            ? rtrim(mb_substr($line, 0, 71, 'UTF-8')).'…'
            : $line;

        return 'Далее: '.$excerpt;
    }

    private function operatorLine(SalesScriptNode $node): ?string
    {
        if ($node->kind === SalesScriptNodeKind::Branch) {
            return null;
        }

        $body = trim((string) $node->body);

        return $body !== '' ? $body : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPresentation(): array
    {
        return [
            'operator_kind' => 'say',
            'operator_line' => null,
            'coaching_hint' => null,
            'branch_instruction' => null,
            'choices' => [],
            'step_key' => null,
            'is_branch_only' => false,
        ];
    }
}
