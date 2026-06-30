<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesPlayEventType;
use App\Enums\SalesPlaySessionOutcome;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTrainerMessage;
use Illuminate\Support\Collection;

/**
 * Составная оценка тренировки: исход воронки + качество диалога + прогресс по графу.
 */
final class TrainerScoreCalculator
{
    public function __construct(
        private readonly TrainerRubricService $trainerRubricService,
    ) {}

    public function calculate(SalesScriptPlaySession $session, SalesPlaySessionOutcome $outcome): int
    {
        $base = $this->baseScoreByOutcome($outcome);
        $session->loadMissing(['trainerMessages', 'events', 'fieldValues.captureField', 'version.script']);

        /** @var Collection<int, SalesScriptTrainerMessage> $assistantMessages */
        $assistantMessages = $session->trainerMessages
            ->where('role', 'assistant')
            ->values();

        $negativeAuto = $assistantMessages
            ->filter(fn (SalesScriptTrainerMessage $m): bool => ($m->auto_peer_reaction?->value ?? '') === 'negative')
            ->count();
        $negativePeer = $assistantMessages
            ->filter(fn (SalesScriptTrainerMessage $m): bool => ($m->peer_reaction?->value ?? '') === 'negative')
            ->count();

        $reactionPenalty = min(25, ($negativeAuto * 3) + ($negativePeer * 5));
        $loopPenalty = min(15, $this->loopPenalty($assistantMessages));

        $nodesVisited = $session->events
            ->where('type', SalesPlayEventType::EnteredNode)
            ->pluck('sales_script_node_id')
            ->unique()
            ->count();
        $progressBonus = min(15, max(0, ($nodesVisited - 1) * 3));

        $messageCount = $session->trainerMessages->count();
        $abandonPenalty = ($messageCount < 4 && ! in_array($outcome, [SalesPlaySessionOutcome::Won, SalesPlaySessionOutcome::QuoteSent], true))
            ? 10
            : 0;

        $rubric = $this->trainerRubricService->forSession($session);
        $rubricAdjustment = (int) round(((int) $rubric['rubric_score'] - 50) / 5);

        return max(0, min(100, $base - $reactionPenalty - $loopPenalty - $abandonPenalty + $progressBonus + $rubricAdjustment));
    }

    private function baseScoreByOutcome(SalesPlaySessionOutcome $outcome): int
    {
        return match ($outcome) {
            SalesPlaySessionOutcome::Won => 100,
            SalesPlaySessionOutcome::QuoteSent => 85,
            SalesPlaySessionOutcome::Progress => 70,
            SalesPlaySessionOutcome::Postponed => 55,
            SalesPlaySessionOutcome::NoContact => 40,
            SalesPlaySessionOutcome::Lost => 20,
        };
    }

    /**
     * @param  Collection<int, SalesScriptTrainerMessage>  $assistantMessages
     */
    private function loopPenalty(Collection $assistantMessages): int
    {
        $penalty = 0;
        $previous = null;

        foreach ($assistantMessages as $message) {
            $normalized = mb_strtolower(trim((string) $message->content), 'UTF-8');
            if ($normalized === '') {
                continue;
            }

            if ($previous !== null && $normalized === $previous) {
                $penalty += 5;
            }

            $previous = $normalized;
        }

        return $penalty;
    }
}
