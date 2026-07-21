<?php

namespace App\Services\Mcp;

use App\Models\Lead;
use App\Models\Task;
use App\Models\User;
use App\Services\LeadBusinessProcessService;
use App\Services\Leads\LeadOperationalBriefService;
use App\Support\LeadStatus;
use App\Support\TaskNumberGenerator;
use App\Support\TaskStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LeadMcpService
{
    /**
     * @var list<string>
     */
    public const UPDATABLE_FIELDS = [
        'title',
        'description',
        'source',
        'transport_type',
        'next_contact_at',
        'status',
        'loading_location',
        'unloading_location',
        'target_price',
        'target_currency',
    ];

    public function __construct(
        private readonly McpAccessGate $access,
        private readonly LeadOperationalBriefService $operationalBrief,
        private readonly LeadBusinessProcessService $businessProcess,
        private readonly TaskNumberGenerator $taskNumbers,
    ) {}

    /**
     * @return array{leads: list<array<string, mixed>>, total: int}
     */
    public function search(User $user, string $query, int $limit = 15, ?string $status = null): array
    {
        $this->access->requireLeadsArea($user);

        if (! Schema::hasTable('leads')) {
            return ['leads' => [], 'total' => 0];
        }

        $needle = trim($query);
        $limit = max(1, min($limit, 25));

        $builder = Lead::query()
            ->with([
                'counterparty:id,name',
                'responsible:id,name',
                'businessProcessStage:id,name',
            ])
            ->orderByDesc('id');

        $this->access->applyLeadsScope($builder, $user);

        if ($status !== null && $status !== '') {
            if (! in_array($status, LeadStatus::values(), true)) {
                throw ValidationException::withMessages([
                    'status' => 'Неизвестный статус лида.',
                ]);
            }

            $builder->where('status', $status);
        }

        if ($needle !== '') {
            $builder->where(function (Builder $scoped) use ($needle): void {
                $scoped->where('number', 'like', '%'.$needle.'%')
                    ->orWhere('title', 'like', '%'.$needle.'%');

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $scoped->orWhere('id', (int) $needle);
                }

                $scoped->orWhereHas('counterparty', function (Builder $counterparty) use ($needle): void {
                    $counterparty->where('name', 'like', '%'.$needle.'%');
                });

                $scoped->orWhereHas('responsible', function (Builder $responsible) use ($needle): void {
                    $responsible->where('name', 'like', '%'.$needle.'%');
                });
            });
        }

        $leads = $builder->limit($limit)->get();

        return [
            'leads' => $leads->map(fn (Lead $lead): array => $this->summarize($lead))->all(),
            'total' => $leads->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(User $user, int $leadId): array
    {
        $lead = $this->access->findAccessibleLead($user, $leadId);
        $lead->load([
            'counterparty:id,name,inn',
            'responsible:id,name',
            'businessProcess:id,name,slug',
            'businessProcessStage:id,name,is_terminal,stage_goal',
        ]);

        $detail = $this->detail($lead);

        $detail['operational_brief'] = $this->operationalBrief->build($lead);
        $detail['process_progress'] = $this->businessProcess->progressPayload($lead);
        $detail['open_tasks'] = $this->openTasksForLead($lead);
        $detail['wizard_path'] = '/leads/'.$lead->id;

        return $detail;
    }

    /**
     * @return array{lead: array<string, mixed>, field: string, previous: mixed, value: mixed}
     */
    public function updateField(User $user, int $leadId, string $field, mixed $value): array
    {
        if (! in_array($field, self::UPDATABLE_FIELDS, true)) {
            throw ValidationException::withMessages([
                'field' => 'Поле «'.$field.'» нельзя менять через MCP. Доступны: '.implode(', ', self::UPDATABLE_FIELDS).'.',
            ]);
        }

        $lead = $this->access->findAccessibleLead($user, $leadId);

        if (LeadStatus::isClosed((string) $lead->status) && $field !== 'description') {
            throw ValidationException::withMessages([
                'field' => 'Закрытый лид можно править только через описание (остальные поля — в UI).',
            ]);
        }

        $previous = $lead->getAttribute($field);
        $normalized = $this->normalizeFieldValue($field, $value);

        $lead->forceFill([
            $field => $normalized,
            'updated_by' => $user->id,
        ])->save();

        return [
            'lead' => $this->detail($lead->fresh([
                'counterparty:id,name,inn',
                'responsible:id,name',
                'businessProcessStage:id,name',
            ])),
            'field' => $field,
            'previous' => $previous,
            'value' => $normalized,
        ];
    }

    /**
     * @param  array{
     *     title: string,
     *     due_at?: string|null,
     *     description?: string|null,
     *     responsible_id?: int|null,
     *     priority?: string|null
     * }  $payload
     * @return array{lead: array<string, mixed>, task: array<string, mixed>}
     */
    public function createNextStep(User $user, int $leadId, array $payload): array
    {
        if (! Schema::hasTable('tasks')) {
            throw new RuntimeException('Таблица задач недоступна.');
        }

        $lead = $this->access->findAccessibleLead($user, $leadId);

        $responsibleId = isset($payload['responsible_id']) && (int) $payload['responsible_id'] > 0
            ? (int) $payload['responsible_id']
            : (int) ($lead->responsible_id ?: $user->id);

        $this->access->ensureCanCreateTask($user, $responsibleId);

        if (! User::query()->whereKey($responsibleId)->exists()) {
            throw ValidationException::withMessages([
                'responsible_id' => 'Указанный ответственный не найден.',
            ]);
        }

        $dueAt = $payload['due_at'] ?? null;
        $title = trim((string) $payload['title']);
        $description = isset($payload['description']) ? trim((string) $payload['description']) : null;
        $priority = (string) ($payload['priority'] ?? 'high');

        $task = Task::query()->create([
            'number' => $this->taskNumbers->next(),
            'title' => $title,
            'description' => $description !== '' ? $description : null,
            'status' => 'new',
            'priority' => $priority,
            'due_at' => $dueAt,
            'responsible_id' => $responsibleId,
            'created_by' => $user->id,
            'lead_id' => $lead->id,
        ]);

        if ($dueAt !== null) {
            $lead->forceFill([
                'next_contact_at' => $dueAt,
                'updated_by' => $user->id,
            ])->save();
        }

        if (Schema::hasTable('lead_activities')) {
            $lead->activities()->create([
                'type' => 'note',
                'subject' => 'Создан следующий шаг',
                'content' => $title,
                'next_action_at' => $dueAt,
                'created_by' => $user->id,
            ]);
        }

        $lead = $lead->fresh([
            'counterparty:id,name,inn',
            'responsible:id,name',
            'businessProcessStage:id,name',
        ]);

        return [
            'lead' => $this->detail($lead),
            'task' => [
                'id' => $task->id,
                'number' => $task->number,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_at' => $task->due_at?->toIso8601String(),
                'responsible_id' => $task->responsible_id,
                'lead_id' => $task->lead_id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summarize(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'number' => $lead->number,
            'title' => $lead->title,
            'status' => $lead->status,
            'status_label' => LeadStatus::label((string) $lead->status),
            'source' => $lead->source,
            'next_contact_at' => $lead->next_contact_at?->toIso8601String(),
            'responsible_id' => $lead->responsible_id,
            'responsible_name' => $lead->responsible?->name,
            'counterparty_id' => $lead->counterparty_id,
            'counterparty_name' => $lead->counterparty?->name,
            'bp_stage_name' => $lead->businessProcessStage?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Lead $lead): array
    {
        $summary = $this->summarize($lead);

        $summary['description'] = $lead->description;
        $summary['transport_type'] = $lead->transport_type;
        $summary['loading_location'] = $lead->loading_location;
        $summary['unloading_location'] = $lead->unloading_location;
        $summary['planned_shipping_date'] = $lead->planned_shipping_date?->toDateString();
        $summary['target_price'] = $lead->target_price;
        $summary['target_currency'] = $lead->target_currency;
        $summary['expected_margin'] = $lead->expected_margin;
        $summary['business_process_id'] = $lead->business_process_id;
        $summary['business_process_stage_id'] = $lead->business_process_stage_id;
        $summary['lead_qualification'] = $lead->lead_qualification;
        $summary['counterparty_inn'] = $lead->counterparty?->inn ?? null;

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openTasksForLead(Lead $lead): array
    {
        if (! Schema::hasTable('tasks')) {
            return [];
        }

        return Task::query()
            ->where('lead_id', $lead->id)
            ->whereIn('status', TaskStatus::openStatuses())
            ->with('responsible:id,name')
            ->orderBy('due_at')
            ->limit(20)
            ->get()
            ->map(static fn (Task $task): array => [
                'id' => $task->id,
                'number' => $task->number,
                'title' => $task->title,
                'status' => $task->status,
                'priority' => $task->priority,
                'due_at' => $task->due_at?->toIso8601String(),
                'responsible_name' => $task->responsible?->name,
            ])
            ->all();
    }

    private function normalizeFieldValue(string $field, mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            if (in_array($field, ['title', 'status', 'target_currency'], true)) {
                throw ValidationException::withMessages([
                    'value' => 'Поле «'.$field.'» нельзя очистить.',
                ]);
            }

            return null;
        }

        return match ($field) {
            'title', 'source', 'transport_type', 'loading_location', 'unloading_location', 'target_currency' => trim((string) $value),
            'description' => trim((string) $value),
            'next_contact_at' => (string) $value,
            'status' => $this->normalizeStatus((string) $value),
            'target_price' => $this->normalizePrice($value),
            default => $value,
        };
    }

    private function normalizeStatus(string $status): string
    {
        $status = trim($status);

        if (! in_array($status, LeadStatus::inlineEditableValues(), true)) {
            throw ValidationException::withMessages([
                'value' => 'Статус через MCP: '.implode(', ', LeadStatus::inlineEditableValues()).'. Закрытие (won/lost) — только в UI.',
            ]);
        }

        return $status;
    }

    private function normalizePrice(mixed $value): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                'value' => 'target_price должно быть числом.',
            ]);
        }

        return round((float) $value, 2);
    }
}
