<?php

namespace App\Services;

use App\Models\ActivityEvent;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Order;
use App\Models\User;
use App\Support\ActivityEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ActivityLedgerService
{
    public function tablesReady(): bool
    {
        return Schema::hasTable('activity_events');
    }

    public function record(
        Model $subject,
        ActivityEventType $type,
        ?string $title = null,
        ?string $summary = null,
        array $payload = [],
        ?Carbon $occurredAt = null,
        ?User $user = null,
        ?Model $source = null,
    ): ?ActivityEvent {
        if (! $this->tablesReady()) {
            return null;
        }

        return ActivityEvent::query()->create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'event_type' => $type->value,
            'title' => $title ?? $type->label(),
            'summary' => $summary,
            'payload' => $payload === [] ? null : $payload,
            'occurred_at' => $occurredAt ?? now(),
            'user_id' => $user?->id,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function timelineForSubject(Model $subject, int $limit = 50): Collection
    {
        if (! $this->tablesReady()) {
            return collect();
        }

        return ActivityEvent::query()
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->with('user:id,name')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (ActivityEvent $event): array => $this->serializeEvent($event));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeEvent(ActivityEvent $event): array
    {
        $type = $event->eventTypeEnum();

        return [
            'id' => $event->id,
            'event_type' => $event->event_type,
            'event_label' => $type?->label() ?? $event->event_type,
            'title' => $event->title,
            'summary' => $event->summary,
            'payload' => $event->payload ?? [],
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'user_name' => $event->user?->name,
        ];
    }

    public function backfillFromLeadActivities(Lead $lead): void
    {
        if (! $this->tablesReady() || ! Schema::hasTable('lead_activities')) {
            return;
        }

        $lead->activities()
            ->where('type', '!=', 'status_change')
            ->orderBy('id')
            ->each(function (LeadActivity $activity) use ($lead): void {
                $exists = ActivityEvent::query()
                    ->where('subject_type', $lead->getMorphClass())
                    ->where('subject_id', $lead->getKey())
                    ->where('source_type', $activity->getMorphClass())
                    ->where('source_id', $activity->getKey())
                    ->exists();

                if ($exists) {
                    return;
                }

                $this->record(
                    $lead,
                    ActivityEventType::NoteAdded,
                    $activity->subject ?: 'Коммуникация',
                    $activity->content,
                    [
                        'legacy_activity_type' => $activity->type,
                        'next_action_at' => optional($activity->next_action_at)?->toIso8601String(),
                    ],
                    $activity->created_at,
                    null,
                    $activity,
                );
            });
    }

    public function subjectFromRequest(string $subjectType, int $subjectId): Model
    {
        return match ($subjectType) {
            'lead' => Lead::query()->findOrFail($subjectId),
            'order' => Order::query()->findOrFail($subjectId),
            default => abort(422, 'Неподдерживаемый тип субъекта таймлайна.'),
        };
    }
}
