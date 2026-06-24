<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\Task;

final class LeadDataChecks
{
    /**
     * @return array<string, bool>
     */
    public static function run(Lead $lead): array
    {
        $qualification = is_array($lead->lead_qualification) ? $lead->lead_qualification : [];

        $routePoints = $lead->relationLoaded('routePoints')
            ? $lead->routePoints
                ->map(fn ($point): array => LeadRoutePointPayloadNormalizer::toFrontend($point))
                ->values()
                ->all()
            : null;

        $cargoItems = $lead->relationLoaded('cargoItems')
            ? $lead->cargoItems
                ->map(fn ($cargo): array => LeadCargoItemPayloadNormalizer::toFrontend($cargo))
                ->values()
                ->all()
            : null;

        $hasOpenTask = false;

        if ($lead->relationLoaded('tasks')) {
            $openStatuses = TaskStatus::openStatuses();
            $hasOpenTask = $lead->tasks->contains(
                fn (Task $task): bool => in_array($task->status, $openStatuses, true),
            );
        }

        $hasOffer = $lead->relationLoaded('offers')
            ? $lead->offers->isNotEmpty()
            : false;

        return [
            'has_counterparty' => $lead->counterparty_id !== null,
            'has_route' => LeadStatusAutoAdvance::hasMeaningfulRoute($routePoints),
            'has_cargo' => LeadStatusAutoAdvance::hasMeaningfulCargo($cargoItems),
            'has_client_price' => $lead->target_price !== null && $lead->target_price !== '',
            'has_offer' => $hasOffer,
            'proposal_sent' => $lead->proposal_sent_at !== null,
            'has_lpr' => filled($qualification['authority'] ?? null),
            'has_open_task' => $hasOpenTask,
            'has_next_contact' => $lead->next_contact_at !== null,
            'close_outcome_set' => filled($lead->close_outcome_primary_flag),
        ];
    }
}
