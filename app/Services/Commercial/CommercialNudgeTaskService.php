<?php

namespace App\Services\Commercial;

use App\Models\Task;
use App\Support\CommercialNudgeType;
use Illuminate\Support\Str;

class CommercialNudgeTaskService
{
    public function hasOpenTask(CommercialNudgeMatch $match): bool
    {
        $type = $match->type;
        $legacyKey = config('commercial_nudges.types.'.$type->value.'.legacy_meta_key');

        $query = Task::query()
            ->where('lead_id', $match->lead->id)
            ->where('status', '!=', 'done');

        $query->where(function ($builder) use ($type, $match, $legacyKey): void {
            $builder->where(function ($modern) use ($type, $match): void {
                $modern->where('meta->commercial_nudge_type', $type->value)
                    ->where('meta->commercial_nudge_subject_id', $match->subjectId);
            });

            if (is_string($legacyKey) && $legacyKey !== '' && $type === CommercialNudgeType::NoReply) {
                $builder->orWhere('meta->'.$legacyKey, $match->subjectId);
            }
        });

        return $query->exists();
    }

    public function createFromMatch(CommercialNudgeMatch $match): Task
    {
        return Task::query()->create([
            'number' => 'T-'.Str::upper(Str::random(8)),
            'title' => $match->title,
            'description' => $match->description,
            'status' => 'new',
            'priority' => $match->priority,
            'due_at' => now()->addDay(),
            'responsible_id' => $match->lead->responsible_id,
            'lead_id' => $match->lead->id,
            'created_by' => null,
            'meta' => [
                'commercial_nudge_type' => $match->type->value,
                'commercial_nudge_subject_id' => $match->subjectId,
            ],
        ]);
    }
}
