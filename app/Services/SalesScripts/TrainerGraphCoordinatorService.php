<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesScriptNodeKind;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTransition;
use InvalidArgumentException;

/**
 * Связывает чат тренажёра с прохождением графа сценария.
 */
final class TrainerGraphCoordinatorService
{
    public function __construct(
        private readonly SalesScriptPlaySessionService $playSessionService,
        private readonly TrainerClientReactionMatcher $reactionMatcher,
    ) {}

    public function afterManagerMessage(SalesScriptPlaySession $session): bool
    {
        if ($session->isComplete() || ! $this->shouldAdvanceGraph($session)) {
            return false;
        }

        $session->loadMissing('currentNode');
        $current = $session->currentNode;
        if ($current === null) {
            return false;
        }

        $outgoing = $this->playSessionService->outgoingTransitions($current);
        $linear = array_values(array_filter(
            $outgoing,
            fn (SalesScriptTransition $t): bool => $t->sales_script_reaction_class_id === null,
        ));
        $reactions = array_values(array_filter(
            $outgoing,
            fn (SalesScriptTransition $t): bool => $t->sales_script_reaction_class_id !== null,
        ));

        if ($current->kind !== SalesScriptNodeKind::Say || count($linear) !== 1) {
            return false;
        }

        try {
            $this->playSessionService->advance($session, null);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    public function afterClientReply(SalesScriptPlaySession $session, string $clientReply): bool
    {
        if ($session->isComplete() || ! $this->shouldAdvanceGraph($session)) {
            return false;
        }

        $session->refresh();
        $session->loadMissing('currentNode');
        $current = $session->currentNode;
        if ($current === null) {
            return false;
        }

        try {
            $match = $this->reactionMatcher->match($current, $clientReply);
            if ($match !== null) {
                $this->playSessionService->advance($session, $match['reaction_class_id']);

                return true;
            }

            if ($current->kind !== SalesScriptNodeKind::Ask) {
                return false;
            }

            $linear = array_values(array_filter(
                $this->playSessionService->outgoingTransitions($current),
                fn (SalesScriptTransition $t): bool => $t->sales_script_reaction_class_id === null,
            ));

            if (count($linear) !== 1) {
                return false;
            }

            $this->playSessionService->advance($session, null);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    private function shouldAdvanceGraph(SalesScriptPlaySession $session): bool
    {
        return ($session->training_role_mode ?: 'manager_seller') === 'manager_seller';
    }
}
