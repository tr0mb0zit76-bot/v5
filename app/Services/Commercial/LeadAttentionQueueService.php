<?php

namespace App\Services\Commercial;

use App\Models\Lead;
use App\Models\User;
use App\Support\CommercialNudgeType;
use App\Support\LeadViewAuthorization;
use App\Support\RoleAccess;
use Illuminate\Support\Facades\Schema;

class LeadAttentionQueueService
{
    public function __construct(
        private readonly CommercialNudgeEvaluator $evaluator,
    ) {}

    /**
     * @return array{available: bool, total: int, items: list<array<string, mixed>>}
     */
    public function queueForUser(?User $user, int $limit = 15): array
    {
        if ($user === null || ! Schema::hasTable('leads')) {
            return [
                'available' => false,
                'total' => 0,
                'items' => [],
            ];
        }

        if (! $this->userCanAccessLeads($user)) {
            return [
                'available' => false,
                'total' => 0,
                'items' => [],
            ];
        }

        $limit = max(1, min(50, $limit));

        $grouped = [];

        Lead::query()
            ->with(['responsible:id,name', 'businessProcessStage:id,name'])
            ->whereNotIn('status', ['won', 'lost'])
            ->tap(fn ($query) => LeadViewAuthorization::applyLeadsVisibilityScope($query, $user))
            ->orderBy('id')
            ->chunkById(100, function ($leads) use (&$grouped): void {
                foreach ($leads as $lead) {
                    foreach ($this->evaluator->matchesForLead($lead) as $match) {
                        $leadId = (int) $lead->id;

                        if (! isset($grouped[$leadId])) {
                            $grouped[$leadId] = [
                                'lead_id' => $leadId,
                                'number' => $lead->number,
                                'title' => $lead->title,
                                'responsible_name' => $lead->responsible?->name,
                                'stage_name' => $lead->businessProcessStage?->name,
                                'reasons' => [],
                            ];
                        }

                        $grouped[$leadId]['reasons'][] = [
                            'type' => $match->type->value,
                            'label' => $match->type->label(),
                            'title' => $match->title,
                            'priority' => $match->priority,
                        ];
                    }
                }
            });

        $items = array_values($grouped);

        usort($items, function (array $left, array $right): int {
            $leftScore = $this->severityScore($left['reasons']);
            $rightScore = $this->severityScore($right['reasons']);

            return $rightScore <=> $leftScore;
        });

        $total = count($items);

        return [
            'available' => true,
            'total' => $total,
            'items' => array_slice($items, 0, $limit),
        ];
    }

    /**
     * @param  list<array{type: string, label: string, title: string, priority: string}>  $reasons
     */
    private function severityScore(array $reasons): int
    {
        $score = 0;

        foreach ($reasons as $reason) {
            $score += match ($reason['type']) {
                CommercialNudgeType::StageOverdue->value => 40,
                CommercialNudgeType::NoReply->value => 30,
                CommercialNudgeType::NextContactMissed->value => 20,
                CommercialNudgeType::LedgerIdle->value => 10,
                default => 5,
            };

            if (($reason['priority'] ?? '') === 'high' || ($reason['priority'] ?? '') === 'critical') {
                $score += 5;
            }
        }

        return $score;
    }

    private function userCanAccessLeads(User $user): bool
    {
        return RoleAccess::canAccessVisibilityArea($user, 'leads');
    }
}
