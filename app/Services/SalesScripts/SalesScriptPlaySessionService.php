<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesPlayEventType;
use App\Enums\SalesPlaySessionOutcome;
use App\Models\Lead;
use App\Models\Order;
use App\Models\SalesScriptCaptureField;
use App\Models\SalesScriptNode;
use App\Models\SalesScriptPlayEvent;
use App\Models\SalesScriptPlaySession;
use App\Models\SalesScriptPlaySessionFieldValue;
use App\Models\SalesScriptTransition;
use App\Models\SalesScriptVersion;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SalesScriptPlaySessionService
{
    public function __construct(
        private readonly SalesScriptPlayContextResolver $playContextResolver,
        private readonly SalesScriptConversationGuidanceService $conversationGuidance,
    ) {}

    public function start(
        SalesScriptVersion $version,
        User $user,
        ?int $contractorId = null,
        ?int $leadId = null,
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

        return DB::transaction(function () use ($version, $user, $contractorId, $leadId, $orderId, $entry): SalesScriptPlaySession {
            $resolvedLeadId = $leadId ?? $this->leadIdFromOrder($orderId);
            $contextTags = $this->playContextResolver->resolveForSession($orderId, $contractorId);

            $session = SalesScriptPlaySession::query()->create([
                'user_id' => $user->id,
                'sales_script_version_id' => $version->id,
                'current_node_id' => $entry->id,
                'contractor_id' => $contractorId,
                'lead_id' => $resolvedLeadId,
                'order_id' => $orderId,
                'context_tags' => $contextTags === [] ? null : $contextTags,
                'started_at' => Carbon::now(),
            ]);

            $this->logEvent($session, SalesPlayEventType::EnteredNode, $entry->id, null, null, [
                'client_key' => $entry->client_key,
            ], $session);
            $this->saveFieldValues(
                $session,
                $entry,
                $this->prefillFieldValues($resolvedLeadId),
            );

            return $session->fresh(['currentNode', 'version.script']);
        });
    }

    /**
     * @return list<SalesScriptTransition>
     */
    public function outgoingTransitions(SalesScriptNode $node): array
    {
        return $node->outgoingTransitions()
            ->with(['reactionClass', 'toNode', 'targetVersion.script'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function advanceCompound(
        SalesScriptPlaySession $session,
        int $reactionClassId,
    ): SalesScriptPlaySession {
        if ($session->isComplete()) {
            throw new InvalidArgumentException('Сессия уже завершена.');
        }

        $current = $session->currentNode;
        if ($current === null) {
            throw new InvalidArgumentException('Нет текущего узла.');
        }

        $linear = $this->resolveTransition($current, null);
        $next = $linear->toNode;
        if ($next === null) {
            throw new InvalidArgumentException('Нет следующего узла для составного перехода.');
        }

        $session = $this->advance($session, null);

        return $this->advance($session->fresh(['currentNode']), $reactionClassId);
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
            $guidance = $this->conversationGuidance->guidanceForTransition($transition);
            $this->logEvent(
                $session,
                SalesPlayEventType::RecordedReaction,
                $current->id,
                $reactionClassId,
                null,
                [
                    'transition_id' => $transition->id,
                    'to_node_id' => $transition->to_node_id,
                    'target_type' => $transition->target_type ?? 'node',
                    'target_sales_script_version_id' => $transition->target_sales_script_version_id,
                    'conversation_effect' => $guidance['effect'],
                    'momentum_delta' => $guidance['momentum_delta'],
                ],
                $session,
            );

            $targetType = (string) ($transition->target_type ?? 'node');
            if ($targetType === 'script') {
                return $this->advanceToSubscript($session, $transition, $current);
            }

            if ($targetType === 'return') {
                return $this->advanceToReturnPoint($session, $transition);
            }

            $next = $transition->toNode;
            $session->update([
                'current_node_id' => $transition->to_node_id,
            ]);

            if ($next !== null) {
                $this->logEvent($session, SalesPlayEventType::EnteredNode, $next->id, null, null, [
                    'client_key' => $next->client_key,
                ], $session);
            }

            return $session->fresh(['currentNode', 'version.script']);
        });
    }

    private function advanceToSubscript(
        SalesScriptPlaySession $session,
        SalesScriptTransition $transition,
        SalesScriptNode $current,
    ): SalesScriptPlaySession {
        $targetVersion = $transition->targetVersion;
        if ($targetVersion === null || ! $targetVersion->isPublished()) {
            throw new InvalidArgumentException('Целевой сценарий не опубликован или не найден.');
        }

        $entryKey = $targetVersion->entry_node_key;
        if ($entryKey === null || $entryKey === '') {
            throw new InvalidArgumentException('У целевого сценария не задан стартовый шаг.');
        }

        /** @var SalesScriptNode|null $entry */
        $entry = $targetVersion->nodes()->where('client_key', $entryKey)->first();
        if ($entry === null) {
            throw new InvalidArgumentException('Стартовый шаг целевого сценария не найден.');
        }

        $stack = $this->normalizedReturnStack($session);
        $stack[] = [
            'return_sales_script_version_id' => (int) $session->sales_script_version_id,
            'return_node_id' => (int) $transition->to_node_id,
            'source_node_id' => (int) $current->id,
            'transition_id' => (int) $transition->id,
        ];

        $session->update([
            'sales_script_version_id' => $targetVersion->id,
            'current_node_id' => $entry->id,
            'return_stack' => $stack,
        ]);

        $this->logEvent($session, SalesPlayEventType::EnteredNode, $entry->id, null, null, [
            'client_key' => $entry->client_key,
            'subscript' => true,
            'from_sales_script_version_id' => $stack[array_key_last($stack)]['return_sales_script_version_id'],
            'return_node_id' => $transition->to_node_id,
        ], $session);

        return $session->fresh(['currentNode', 'version.script']);
    }

    private function advanceToReturnPoint(
        SalesScriptPlaySession $session,
        SalesScriptTransition $transition,
    ): SalesScriptPlaySession {
        $stack = $this->normalizedReturnStack($session);
        $frame = array_pop($stack);

        if (! is_array($frame)) {
            $next = $transition->toNode;
            $session->update([
                'current_node_id' => $transition->to_node_id,
                'return_stack' => null,
            ]);

            if ($next !== null) {
                $this->logEvent($session, SalesPlayEventType::EnteredNode, $next->id, null, null, [
                    'client_key' => $next->client_key,
                    'return_fallback' => true,
                ], $session);
            }

            return $session->fresh(['currentNode', 'version.script']);
        }

        $returnVersionId = (int) ($frame['return_sales_script_version_id'] ?? 0);
        $returnNodeId = (int) ($frame['return_node_id'] ?? 0);
        $returnNode = SalesScriptNode::query()
            ->whereKey($returnNodeId)
            ->where('sales_script_version_id', $returnVersionId)
            ->first();

        if ($returnVersionId <= 0 || $returnNode === null) {
            throw new InvalidArgumentException('Точка возврата в исходный сценарий не найдена.');
        }

        $session->update([
            'sales_script_version_id' => $returnVersionId,
            'current_node_id' => $returnNode->id,
            'return_stack' => $stack === [] ? null : $stack,
        ]);

        $this->logEvent($session, SalesPlayEventType::EnteredNode, $returnNode->id, null, null, [
            'client_key' => $returnNode->client_key,
            'returned_from_subscript' => true,
        ], $session);

        return $session->fresh(['currentNode', 'version.script']);
    }

    /**
     * @param  array<string, string|null>  $fieldValuesByCode
     */
    public function saveFieldValues(
        SalesScriptPlaySession $session,
        SalesScriptNode $node,
        array $fieldValuesByCode,
    ): void {
        if ($fieldValuesByCode === []) {
            return;
        }

        $codes = array_keys($fieldValuesByCode);
        $fields = SalesScriptCaptureField::query()
            ->whereIn('code', $codes)
            ->get()
            ->keyBy('code');

        DB::transaction(function () use ($session, $node, $fieldValuesByCode, $fields): void {
            foreach ($fieldValuesByCode as $code => $value) {
                $field = $fields->get($code);
                if ($field === null) {
                    continue;
                }

                $trimmed = trim((string) $value);
                if ($trimmed === '') {
                    continue;
                }

                SalesScriptPlaySessionFieldValue::query()->updateOrCreate(
                    [
                        'sales_script_play_session_id' => $session->id,
                        'sales_script_capture_field_id' => $field->id,
                    ],
                    [
                        'value' => $trimmed,
                        'captured_at_node_id' => $node->id,
                    ],
                );
            }
        });
    }

    public function complete(
        SalesScriptPlaySession $session,
        SalesPlaySessionOutcome $outcome,
        ?int $primaryReactionClassId = null,
        ?string $notes = null,
        ?int $leadId = null,
        ?int $orderId = null,
    ): SalesScriptPlaySession {
        if ($session->isComplete()) {
            throw new InvalidArgumentException('Сессия уже завершена.');
        }

        return DB::transaction(function () use ($session, $outcome, $primaryReactionClassId, $notes, $leadId, $orderId): SalesScriptPlaySession {
            $updates = [
                'outcome' => $outcome,
                'primary_reaction_class_id' => $primaryReactionClassId,
                'notes' => $notes,
                'completed_at' => Carbon::now(),
            ];

            $resolvedLeadId = $leadId ?? $this->leadIdFromOrder($orderId);
            if ($resolvedLeadId !== null) {
                $updates['lead_id'] = $resolvedLeadId;
            }

            if ($orderId !== null) {
                $updates['order_id'] = $orderId;
                $updates['context_tags'] = $this->playContextResolver->resolveForSession(
                    $orderId,
                    $session->contractor_id,
                ) ?: null;
            }

            $session->update($updates);

            $this->logEvent(
                $session,
                SalesPlayEventType::Completed,
                $session->current_node_id,
                null,
                $notes,
                ['outcome' => $outcome->value],
                $session->fresh(),
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

    /**
     * @return array<string, string>
     */
    private function prefillFieldValues(?int $leadId): array
    {
        if ($leadId === null || ! Schema::hasTable('leads')) {
            return [];
        }

        $lead = Lead::query()->with('counterparty:id,name')->find($leadId);
        if ($lead === null) {
            return [];
        }

        return array_filter([
            'client_name' => $lead->counterparty?->name ?: $lead->title,
            'route_from' => $lead->loading_location,
            'route_to' => $lead->unloading_location,
            'loading_date' => $lead->planned_shipping_date?->toDateString(),
            'decision_deadline' => $lead->next_contact_at?->toDateString(),
        ], fn (mixed $value): bool => is_string($value) && trim($value) !== '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function normalizedReturnStack(SalesScriptPlaySession $session): array
    {
        $stack = $session->return_stack;

        return is_array($stack) ? array_values(array_filter($stack, 'is_array')) : [];
    }

    private function leadIdFromOrder(?int $orderId): ?int
    {
        if ($orderId === null || ! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'lead_id')) {
            return null;
        }

        $leadId = Order::query()
            ->whereKey($orderId)
            ->value('lead_id');

        return is_numeric($leadId) ? (int) $leadId : null;
    }

    private function logEvent(
        SalesScriptPlaySession $session,
        SalesPlayEventType $type,
        ?int $nodeId,
        ?int $reactionClassId,
        ?string $body,
        ?array $meta,
        ?SalesScriptPlaySession $contextSource = null,
    ): void {
        $metaPayload = is_array($meta) ? $meta : [];
        $contextTags = $this->playContextResolver->tagsForSession($contextSource ?? $session);
        if ($contextTags !== []) {
            $metaPayload['context_tags'] = $contextTags;
        }

        SalesScriptPlayEvent::query()->create([
            'sales_script_play_session_id' => $session->id,
            'type' => $type,
            'sales_script_node_id' => $nodeId,
            'sales_script_reaction_class_id' => $reactionClassId,
            'body' => $body,
            'meta' => $metaPayload === [] ? null : $metaPayload,
            'created_at' => Carbon::now(),
        ]);
    }
}
