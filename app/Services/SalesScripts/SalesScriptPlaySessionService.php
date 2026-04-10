<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesPlayEventType;
use App\Enums\SalesPlaySessionOutcome;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlayEvent;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SalesScriptPlaySessionService
{
    public function start(
        SalesScriptVersion $version,
        User $user,
        ?int $contractorId = null,
        ?int $orderId = null,
    ): SalesScriptPlaySession {
        if (! $version->isPublished()) {
            throw new InvalidArgumentException('Версия сценария не опубликована.');
        }

        $entryKey = $version->entry_node_key;
        if ($entryKey === null || $entryKey === '') {
            throw new InvalidArgumentException('У версии не задан entry_node_key.');
        }

        /** @var SalesScriptNode|null $entry */
        $entry = $version->nodes()->where('client_key', $entryKey)->first();
        if ($entry === null) {
            throw new InvalidArgumentException('Стартовый узел не найден.');
        }

        return DB::transaction(function () use ($version, $user, $contractorId, $orderId, $entry): SalesScriptPlaySession {
            $session = SalesScriptPlaySession::query()->create([
                'user_id' => $user->id,
                'sales_script_version_id' => $version->id,
                'current_node_id' => $entry->id,
                'contractor_id' => $contractorId,
                'order_id' => $orderId,
                'started_at' => Carbon::now(),
            ]);

            $this->logEvent($session, SalesPlayEventType::EnteredNode, $entry->id, null, null, [
                'client_key' => $entry->client_key,
            ]);

            return $session->fresh(['currentNode', 'version.script']);
        });
    }

    /**
     * @return list<SalesScriptTransition>
     */
    public function outgoingTransitions(SalesScriptNode $node): array
    {
        return $node->outgoingTransitions()
            ->with('reactionClass')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function advance(
        SalesScriptPlaySession $session,
        ?int $reactionClassId,
    ): SalesScriptPlaySession {
        if ($session->isComplete()) {
            throw new InvalidArgumentException('Сессия уже завершена.');
        }

        $current = $session->currentNode;
        if ($current === null) {
            throw new InvalidArgumentException('Нет текущего узла.');
        }

        $transition = $this->resolveTransition($current, $reactionClassId);

        return DB::transaction(function () use ($session, $transition, $reactionClassId, $current): SalesScriptPlaySession {
            $this->logEvent(
                $session,
                SalesPlayEventType::RecordedReaction,
                $current->id,
                $reactionClassId,
                null,
                ['to_node_id' => $transition->to_node_id],
            );

            $session->update([
                'current_node_id' => $transition->to_node_id,
            ]);

            $next = $transition->toNode;
            if ($next !== null) {
                $this->logEvent($session, SalesPlayEventType::EnteredNode, $next->id, null, null, [
                    'client_key' => $next->client_key,
                ]);
            }

            return $session->fresh(['currentNode', 'version.script']);
        });
    }

    public function complete(
        SalesScriptPlaySession $session,
        SalesPlaySessionOutcome $outcome,
        ?int $primaryReactionClassId = null,
        ?string $notes = null,
    ): SalesScriptPlaySession {
        if ($session->isComplete()) {
            throw new InvalidArgumentException('Сессия уже завершена.');
        }

        return DB::transaction(function () use ($session, $outcome, $primaryReactionClassId, $notes): SalesScriptPlaySession {
            $session->update([
                'outcome' => $outcome,
                'primary_reaction_class_id' => $primaryReactionClassId,
                'notes' => $notes,
                'completed_at' => Carbon::now(),
            ]);

            $this->logEvent(
                $session,
                SalesPlayEventType::Completed,
                $session->current_node_id,
                null,
                $notes,
                ['outcome' => $outcome->value],
            );

            return $session->fresh(['currentNode', 'version.script']);
        });
    }

    private function resolveTransition(SalesScriptNode $node, ?int $reactionClassId): SalesScriptTransition
    {
        $query = $node->outgoingTransitions()->orderBy('sort_order')->orderBy('id');

        if ($reactionClassId === null) {
            /** @var SalesScriptTransition|null $t */
            $t = (clone $query)->whereNull('sales_script_reaction_class_id')->first();
            if ($t === null) {
                throw new InvalidArgumentException('Нет перехода «Дальше» для этого узла.');
            }

            return $t;
        }

        /** @var SalesScriptTransition|null $t */
        $t = (clone $query)->where('sales_script_reaction_class_id', $reactionClassId)->first();
        if ($t === null) {
            throw new InvalidArgumentException('Нет перехода для выбранной реакции.');
        }

        return $t;
    }

    private function logEvent(
        SalesScriptPlaySession $session,
        SalesPlayEventType $type,
        ?int $nodeId,
        ?int $reactionClassId,
        ?string $body,
        ?array $meta,
    ): void {
        SalesScriptPlayEvent::query()->create([
            'sales_script_play_session_id' => $session->id,
            'type' => $type,
            'sales_script_node_id' => $nodeId,
            'sales_script_reaction_class_id' => $reactionClassId,
            'body' => $body,
            'meta' => $meta,
            'created_at' => Carbon::now(),
        ]);
    }
}
