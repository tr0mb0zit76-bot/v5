<?php

namespace App\Services\Mobile;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\Task;
use App\Models\User;
use App\Services\MessengerService;
use App\Services\OrderDocumentRequirementService;
use App\Services\TaskSlaService;
use App\Support\LeadStatus;
use App\Support\LeadViewAuthorization;
use App\Support\OrderDocumentAccessAuthorization;
use App\Support\OrderViewAuthorization;
use App\Support\RoleAccess;
use App\Support\TaskStatus;
use App\Support\TaskViewAuthorization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class MobileShellFeedService
{
    public function __construct(
        private MessengerService $messengerService,
        private OrderDocumentRequirementService $documentRequirementService,
        private TaskSlaService $taskSlaService,
    ) {}

    /**
     * @return array{tasks: list<array<string, mixed>>, overdue_count: int}
     */
    public function tasksForUser(User $user, ?string $search = null): array
    {
        if (! Schema::hasTable('tasks') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks')) {
            return ['tasks' => [], 'overdue_count' => 0];
        }

        $needle = trim((string) $search);

        $query = Task::query()
            ->with(['responsible:id,name', 'lead:id,number', 'contractor:id,name'])
            ->where('status', '!=', 'done')
            ->tap(fn ($builder) => TaskViewAuthorization::applyTasksVisibilityScope($builder, $user));

        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($builder) use ($like, $needle): void {
                $builder->where('title', 'like', $like)
                    ->orWhere('number', 'like', $like);

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $builder->orWhere('id', (int) $needle);
                }
            });
        }

        $tasks = $query
            ->orderByRaw('case when due_at is null then 1 else 0 end')
            ->orderBy('due_at')
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $overdueCount = 0;
        $items = [];

        foreach ($tasks as $task) {
            $isOverdue = filled($task->due_at) && $task->due_at->isPast();
            if ($isOverdue) {
                $overdueCount++;
            }

            $items[] = [
                'id' => $task->id,
                'number' => $task->number,
                'title' => $task->title,
                'status' => $task->status,
                'status_label' => TaskStatus::label((string) $task->status),
                'priority' => $task->priority,
                'due_at' => optional($task->due_at)?->toIso8601String(),
                'is_overdue' => $isOverdue,
                'sla_breached' => Schema::hasColumn('tasks', 'sla_deadline_at')
                    ? $this->taskSlaService->isSlaBreached($task)
                    : false,
                'responsible_name' => $task->responsible?->name,
                'responsible_id' => $task->responsible_id ? (int) $task->responsible_id : null,
                'lead_id' => $task->lead_id ? (int) $task->lead_id : null,
                'lead_number' => $task->lead?->number,
                'order_id' => $task->order_id ? (int) $task->order_id : null,
                'contractor_name' => $task->contractor?->name,
                'url' => route('tasks.show', $task, absolute: true),
                'order_url' => $task->order_id
                    ? route('orders.edit', (int) $task->order_id, absolute: true)
                    : null,
                'lead_url' => $task->lead_id && $task->lead
                    ? route('leads.show', $task->lead, absolute: true)
                    : null,
            ];
        }

        return [
            'tasks' => $items,
            'overdue_count' => $overdueCount,
        ];
    }

    /**
     * @return array{orders: list<array<string, mixed>>}
     */
    public function ordersForUser(User $user, ?string $search = null): array
    {
        if (! Schema::hasTable('orders') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders')) {
            return ['orders' => []];
        }

        $needle = trim((string) $search);

        $query = $this->buildVisibleOrdersQuery($user)
            ->with(['client:id,name', 'carrier:id,name']);

        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($builder) use ($like, $needle): void {
                $builder->where('order_number', 'like', $like);

                if (Schema::hasColumn('orders', 'order_customer_number')) {
                    $builder->orWhere('order_customer_number', 'like', $like);
                }

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $builder->orWhere('orders.id', (int) $needle);
                }
            });
        }

        $orders = $query
            ->orderByDesc('updated_at')
            ->limit(40)
            ->get();

        $items = $orders
            ->map(fn (Order $order): array => $this->serializeOrderForMobileShell($order))
            ->values()
            ->all();

        return ['orders' => $items];
    }

    /**
     * @return array{contractors: list<array<string, mixed>>}
     */
    public function documentContractorsForUser(User $user, ?string $search = null): array
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('contractors')
            || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders')) {
            return ['contractors' => []];
        }

        $needle = trim((string) $search);
        $baseQuery = $this->buildVisibleOrdersQuery($user);

        $customerPairs = (clone $baseQuery)
            ->selectRaw('customer_id as contractor_id, orders.id as order_id, orders.updated_at as order_updated_at')
            ->whereNotNull('customer_id');

        $carrierPairs = (clone $baseQuery)
            ->selectRaw('carrier_id as contractor_id, orders.id as order_id, orders.updated_at as order_updated_at')
            ->whereNotNull('carrier_id');

        $pairsQuery = $customerPairs->unionAll($carrierPairs);

        $contractors = Contractor::query()
            ->joinSub($pairsQuery, 'pairs', function ($join): void {
                $join->on('contractors.id', '=', 'pairs.contractor_id');
            })
            ->when($needle !== '', function (Builder $query) use ($needle): void {
                $like = '%'.$needle.'%';
                $query->where(function (Builder $builder) use ($like, $needle): void {
                    $builder->where('contractors.name', 'like', $like)
                        ->orWhere('contractors.inn', 'like', $like);

                    if (preg_match('/^\d+$/', $needle) === 1) {
                        $builder->orWhere('contractors.id', (int) $needle);
                    }
                });
            })
            ->groupBy('contractors.id', 'contractors.name', 'contractors.inn')
            ->selectRaw('contractors.id, contractors.name, contractors.inn, count(distinct pairs.order_id) as orders_count, max(pairs.order_updated_at) as last_activity_at')
            ->orderByDesc('last_activity_at')
            ->limit(50)
            ->get();

        $items = $contractors->map(fn (Contractor $row): array => [
            'id' => (int) $row->id,
            'name' => (string) $row->name,
            'inn' => filled($row->inn) ? (string) $row->inn : null,
            'orders_count' => (int) $row->orders_count,
            'last_activity_at' => filled($row->last_activity_at)
                ? Carbon::parse((string) $row->last_activity_at)->toIso8601String()
                : null,
        ])->all();

        return ['contractors' => $items];
    }

    /**
     * @return array{contractor: array<string, mixed>, orders: list<array<string, mixed>>}
     */
    public function documentOrdersForContractor(User $user, Contractor $contractor, ?string $search = null): array
    {
        abort_unless(
            Schema::hasTable('orders')
            && RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders'),
            403,
        );

        $needle = trim((string) $search);

        $query = $this->buildVisibleOrdersQuery($user)
            ->with(['client:id,name', 'carrier:id,name'])
            ->where(function (Builder $builder) use ($contractor): void {
                $builder->where('customer_id', $contractor->id)
                    ->orWhere('carrier_id', $contractor->id);
            });

        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function (Builder $builder) use ($like, $needle): void {
                $builder->where('order_number', 'like', $like);

                if (Schema::hasColumn('orders', 'order_customer_number')) {
                    $builder->orWhere('order_customer_number', 'like', $like);
                }

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $builder->orWhere('orders.id', (int) $needle);
                }
            });
        }

        $orders = $query
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get()
            ->map(fn (Order $order): array => $this->serializeOrderForMobileShell($order))
            ->values()
            ->all();

        return [
            'contractor' => [
                'id' => (int) $contractor->id,
                'name' => (string) $contractor->name,
                'inn' => filled($contractor->inn) ? (string) $contractor->inn : null,
            ],
            'orders' => $orders,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderDocumentChecklistForUser(User $user, Order $order): array
    {
        abort_unless($this->userCanViewOrder($user, $order), 403);

        $order->loadMissing(['client:id,name', 'carrier:id,name', 'documents']);
        $checklist = $this->documentRequirementService->checklistForOrder($order);
        $documentsById = $order->documents->keyBy(fn (OrderDocument $document): int => (int) $document->getKey());
        $pending = collect($checklist)->filter(fn (array $item): bool => ! ($item['completed'] ?? false));
        $completed = collect($checklist)->filter(fn (array $item): bool => (bool) ($item['completed'] ?? false));

        $slots = array_map(function (array $item) use ($order, $documentsById): array {
            $matchedId = isset($item['matched_document_id']) ? (int) $item['matched_document_id'] : 0;
            $document = $matchedId > 0 ? $documentsById->get($matchedId) : null;

            return [
                'key' => (string) ($item['key'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'party' => (string) ($item['party'] ?? 'internal'),
                'completed' => (bool) ($item['completed'] ?? false),
                'document' => $document instanceof OrderDocument ? [
                    'id' => (int) $document->getKey(),
                    'type' => (string) $document->type,
                    'original_name' => $document->original_name,
                    'url' => route('orders.edit', $order, absolute: true).'?tab=documents',
                ] : null,
            ];
        }, $checklist);

        return [
            'order' => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number ?: '#'.$order->id,
                'customer_id' => $order->customer_id ? (int) $order->customer_id : null,
                'customer_name' => $order->client?->name,
                'carrier_name' => $order->carrier?->name,
                'status' => $order->manual_status ?: $order->status,
            ],
            'documents' => [
                'pending_count' => $pending->count(),
                'completed_count' => $completed->count(),
                'total_count' => count($checklist),
            ],
            'slots' => $slots,
            'urls' => [
                'order' => route('orders.edit', $order, absolute: true),
                'documents' => route('orders.edit', $order, absolute: true).'?tab=documents',
            ],
        ];
    }

    /**
     * @return array{
     *     recent: list<array<string, mixed>>,
     *     attention: list<array<string, mixed>>
     * }
     */
    public function documentsForUser(User $user, ?string $search = null): array
    {
        $recent = $this->messengerService->orderDocumentsForChips($user, $search !== null && trim($search) !== '' ? $search : null);

        $attention = [];

        if (($search === null || trim($search) === '') && Schema::hasTable('orders')) {
            $attention = $this->attentionOrdersForUser($user);
        }

        return [
            'recent' => $recent,
            'attention' => $attention,
        ];
    }

    /**
     * @return array{leads: list<array<string, mixed>>}
     */
    public function trakloLeadsForUser(User $user, ?string $search = null): array
    {
        if (! Schema::hasTable('leads') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads')) {
            return ['leads' => []];
        }

        $needle = trim((string) $search);

        $query = Lead::query()
            ->with(['counterparty:id,name', 'responsible:id,name'])
            ->whereIn('source', ['traklo_public_request', 'traklo_message_intake'])
            ->whereNotIn('status', ['won', 'lost'])
            ->tap(fn ($builder) => LeadViewAuthorization::applyLeadsVisibilityScope($builder, $user, includeUnassigned: true));

        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($builder) use ($like, $needle): void {
                $builder->where('number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('loading_location', 'like', $like)
                    ->orWhere('unloading_location', 'like', $like);

                if (preg_match('/^\d+$/', $needle) === 1) {
                    $builder->orWhere('id', (int) $needle);
                }
            });
        }

        $leads = $query
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $items = $leads->map(function (Lead $lead): array {
            $request = is_array($lead->metadata)
                ? ($lead->metadata['public_transport_request'] ?? $lead->metadata['traklo_message_intake'] ?? [])
                : [];

            return [
                'id' => (int) $lead->id,
                'number' => filled($lead->number) ? (string) $lead->number : '#'.$lead->id,
                'source' => $lead->source,
                'title' => $lead->title,
                'status' => $lead->status,
                'status_label' => LeadStatus::label((string) $lead->status),
                'counterparty_name' => $lead->counterparty?->name,
                'responsible_name' => $lead->responsible?->name,
                'contact_name' => is_array($request) ? ($request['contact_name'] ?? null) : null,
                'company_name' => is_array($request) ? ($request['company_name'] ?? null) : null,
                'phone' => is_array($request) ? ($request['phone'] ?? $request['contact_phone'] ?? null) : null,
                'cargo' => is_array($request) ? ($request['cargo'] ?? null) : null,
                'loading_location' => $lead->loading_location,
                'unloading_location' => $lead->unloading_location,
                'planned_shipping_date' => optional($lead->planned_shipping_date)?->toDateString(),
                'created_at' => optional($lead->created_at)?->toIso8601String(),
                'url' => route('leads.show', $lead, absolute: true),
            ];
        })->values()->all();

        return ['leads' => $items];
    }

    /**
     * @return array{
     *     order: array{id: int, order_number: string, customer_name: string|null},
     *     slots: list<array<string, mixed>>
     * }
     */
    public function orderDocumentUploadOptions(User $user, Order $order): array
    {
        abort_unless(
            OrderDocumentAccessAuthorization::userMayManageDocuments($user, $order),
            403,
        );

        $order->loadMissing(['client:id,name']);
        $rules = $this->documentRequirementService->requirementRulesForOrder($order);
        $checklist = collect($this->documentRequirementService->checklistForOrder($order))->keyBy('key');

        $slots = [];

        foreach ($rules as $rule) {
            $key = (string) ($rule['key'] ?? '');
            $acceptedTypes = $rule['accepted_types'] ?? ['other'];

            $slots[] = [
                'key' => $key,
                'label' => (string) ($rule['label'] ?? $key),
                'party' => (string) ($rule['party'] ?? 'internal'),
                'type' => (string) ($acceptedTypes[0] ?? 'other'),
                'requirement_slot_key' => (string) ($rule['slot_key'] ?? $key),
                'order_leg_stage' => $rule['order_leg_stage'] ?? null,
                'contractor_id' => isset($rule['contractor_id']) ? (int) $rule['contractor_id'] : null,
                'completed' => (bool) ($checklist->get($key)['completed'] ?? false),
            ];
        }

        return [
            'order' => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number ?: '#'.$order->id,
                'customer_name' => $order->client?->name,
            ],
            'slots' => $slots,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function orderSummaryForUser(User $user, Order $order): array
    {
        abort_unless($this->userCanViewOrder($user, $order), 403);

        $order->loadMissing(['client:id,name', 'carrier:id,name']);
        $checklist = $this->documentRequirementService->checklistForOrder($order);
        $pending = collect($checklist)->filter(fn (array $item): bool => ! ($item['completed'] ?? false));
        $completed = collect($checklist)->filter(fn (array $item): bool => (bool) ($item['completed'] ?? false));

        return [
            'order' => [
                'id' => (int) $order->id,
                'order_number' => $order->order_number ?: '#'.$order->id,
                'customer_name' => $order->client?->name,
                'carrier_name' => $order->carrier?->name,
                'status' => $order->manual_status ?: $order->status,
                'loading_date' => $order->loading_date,
                'unloading_date' => $order->unloading_date,
            ],
            'documents' => [
                'pending_count' => $pending->count(),
                'completed_count' => $completed->count(),
                'total_count' => count($checklist),
                'pending' => $pending->take(8)->map(fn (array $item): array => [
                    'label' => (string) ($item['label'] ?? ''),
                ])->values()->all(),
            ],
            'urls' => [
                'order' => route('orders.edit', $order, absolute: true),
                'documents' => route('orders.edit', $order, absolute: true).'?tab=documents',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function leadSummaryForUser(User $user, Lead $lead): array
    {
        abort_unless($this->userCanViewLead($user, $lead), 403);

        $lead->loadMissing(['counterparty:id,name', 'responsible:id,name']);

        $intake = $this->trakloIntakeMetadata($lead);

        return [
            'lead' => [
                'id' => (int) $lead->id,
                'number' => $lead->number,
                'title' => $lead->title,
                'status' => $lead->status,
                'status_label' => LeadStatus::label((string) $lead->status),
                'source' => $lead->source,
                'counterparty_name' => $lead->counterparty?->name,
                'responsible_name' => $lead->responsible?->name,
                'loading_location' => $lead->loading_location,
                'unloading_location' => $lead->unloading_location,
                'planned_shipping_date' => optional($lead->planned_shipping_date)?->toDateString(),
                'cargo' => $intake['cargo'] ?? null,
                'phone' => $intake['phone'] ?? null,
                'contact_name' => $intake['contact_name'] ?? null,
                'company_name' => $intake['company_name'] ?? null,
                'raw_text' => $intake['raw_text'] ?? null,
                'parser' => $intake['parser'] ?? null,
                'editable' => $this->userCanEditTrakloLeadDraft($user, $lead),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateLeadDraftForUser(User $user, Lead $lead, array $payload): array
    {
        abort_unless($this->userCanEditTrakloLeadDraft($user, $lead), 403);

        $metadataKey = $this->trakloIntakeMetadataKey($lead);
        $metadata = is_array($lead->metadata) ? $lead->metadata : [];
        $intake = is_array($metadata[$metadataKey] ?? null) ? $metadata[$metadataKey] : [];

        if (array_key_exists('loading_location', $payload)) {
            $lead->loading_location = filled($payload['loading_location']) ? (string) $payload['loading_location'] : null;
        }

        if (array_key_exists('unloading_location', $payload)) {
            $lead->unloading_location = filled($payload['unloading_location']) ? (string) $payload['unloading_location'] : null;
        }

        if (array_key_exists('title', $payload)) {
            $lead->title = filled($payload['title']) ? (string) $payload['title'] : $lead->title;
        }

        if (array_key_exists('planned_shipping_date', $payload)) {
            $lead->planned_shipping_date = filled($payload['planned_shipping_date'])
                ? (string) $payload['planned_shipping_date']
                : null;
        }

        foreach (['cargo', 'contact_name', 'company_name'] as $field) {
            if (array_key_exists($field, $payload)) {
                $intake[$field] = filled($payload[$field]) ? (string) $payload[$field] : null;
            }
        }

        if (array_key_exists('phone', $payload)) {
            $phone = filled($payload['phone']) ? (string) $payload['phone'] : null;
            $intake['phone'] = $phone;
            $intake['contact_phone'] = $phone;
        }

        $intake['edited_in_traklo_at'] = now()->toIso8601String();
        $intake['edited_in_traklo_by'] = $user->id;
        $metadata[$metadataKey] = $intake;
        $lead->metadata = $metadata;
        $lead->updated_by = $user->id;
        $lead->save();

        return $this->leadSummaryForUser($user, $lead->fresh(['counterparty:id,name', 'responsible:id,name']));
    }

    /**
     * @return array<string, mixed>
     */
    private function trakloIntakeMetadata(Lead $lead): array
    {
        if (! is_array($lead->metadata)) {
            return [];
        }

        $key = $this->trakloIntakeMetadataKey($lead);
        $intake = is_array($lead->metadata[$key] ?? null) ? $lead->metadata[$key] : [];

        if (! isset($intake['phone']) && isset($intake['contact_phone'])) {
            $intake['phone'] = $intake['contact_phone'];
        }

        return $intake;
    }

    private function trakloIntakeMetadataKey(Lead $lead): string
    {
        return $lead->source === 'traklo_message_intake'
            ? 'traklo_message_intake'
            : 'public_transport_request';
    }

    private function userCanEditTrakloLeadDraft(User $user, Lead $lead): bool
    {
        if (! $this->userCanViewLead($user, $lead)) {
            return false;
        }

        if (! in_array((string) $lead->source, ['traklo_public_request', 'traklo_message_intake'], true)) {
            return false;
        }

        return ! LeadStatus::isClosed((string) $lead->status);
    }

    /**
     * @return array<string, mixed>
     */
    public function contractorSummaryForUser(User $user, Contractor $contractor): array
    {
        abort_unless($this->userCanViewContractor($user, $contractor), 403);

        return [
            'contractor' => [
                'id' => (int) $contractor->id,
                'name' => $contractor->name,
                'inn' => $contractor->inn,
                'phone' => $contractor->phone,
                'contact_person' => $contractor->contact_person,
                'contact_person_phone' => $contractor->contact_person_phone,
            ],
            'urls' => [
                'contractor' => route('contractors.show', $contractor, absolute: true),
            ],
        ];
    }

    /**
     * @return array{kind: string, label: string, title: string, subtitle: string|null}|null
     */
    public function linkPreviewForUser(User $user, string $url): ?array
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            return null;
        }

        $query = parse_url($url, PHP_URL_QUERY);
        $queryString = is_string($query) ? $query : '';

        if (preg_match('#/orders/(\d+)#', $path, $matches) === 1) {
            $order = Order::query()->with(['client:id,name'])->find((int) $matches[1]);
            if ($order === null || ! $this->userCanViewOrder($user, $order)) {
                return null;
            }

            $orderNumber = $order->order_number ?: '#'.$order->id;
            $documentsTab = str_contains($queryString, 'tab=documents');

            return [
                'kind' => $documentsTab ? 'document' : 'order',
                'label' => $documentsTab ? 'Документы' : 'Заказ',
                'title' => $documentsTab ? "Документы · {$orderNumber}" : $orderNumber,
                'subtitle' => $order->client?->name,
            ];
        }

        if (preg_match('#/leads/(\d+)#', $path, $matches) === 1) {
            $lead = Lead::query()->with(['counterparty:id,name'])->find((int) $matches[1]);
            if ($lead === null || ! $this->userCanViewLead($user, $lead)) {
                return null;
            }

            $number = filled($lead->number) ? (string) $lead->number : '#'.$lead->id;

            return [
                'kind' => 'lead',
                'label' => 'Лид',
                'title' => $number,
                'subtitle' => $lead->title ?: $lead->counterparty?->name,
            ];
        }

        if (preg_match('#/contractors/(\d+)#', $path, $matches) === 1) {
            $contractor = Contractor::query()->find((int) $matches[1]);
            if ($contractor === null || ! $this->userCanViewContractor($user, $contractor)) {
                return null;
            }

            return [
                'kind' => 'contractor',
                'label' => 'Контрагент',
                'title' => (string) $contractor->name,
                'subtitle' => filled($contractor->inn) ? 'ИНН '.$contractor->inn : null,
            ];
        }

        if (preg_match('#/tasks/(\d+)#', $path, $matches) === 1) {
            $task = Task::query()->find((int) $matches[1]);
            if ($task === null || ! $this->userCanViewTask($user, $task)) {
                return null;
            }

            $number = filled($task->number) ? (string) $task->number : '#'.$task->id;

            return [
                'kind' => 'task',
                'label' => 'Задача',
                'title' => trim($number.' · '.(string) $task->title),
                'subtitle' => TaskStatus::label((string) $task->status),
            ];
        }

        return null;
    }

    private function userCanViewLead(User $user, Lead $lead): bool
    {
        if (! Schema::hasTable('leads') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads')) {
            return false;
        }

        if ($this->isVisibleIncomingTrakloLead($lead)) {
            return true;
        }

        return LeadViewAuthorization::userCanViewLead($user, $lead);
    }

    private function isVisibleIncomingTrakloLead(Lead $lead): bool
    {
        return in_array((string) $lead->source, ['traklo_public_request', 'traklo_message_intake'], true)
            && $lead->responsible_id === null
            && ! LeadStatus::isClosed((string) $lead->status);
    }

    private function userCanViewContractor(User $user, Contractor $contractor): bool
    {
        if (! Schema::hasTable('contractors') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'contractors')) {
            return false;
        }

        return true;
    }

    private function userCanViewTask(User $user, Task $task): bool
    {
        if (! Schema::hasTable('tasks') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'tasks')) {
            return false;
        }

        return TaskViewAuthorization::userCanViewTask($user, $task);
    }

    private function userCanViewOrder(User $user, Order $order): bool
    {
        return OrderViewAuthorization::userCanViewOrder($user, $order);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attentionOrdersForUser(User $user): array
    {
        if (! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders')) {
            return [];
        }

        $query = $this->buildVisibleOrdersQuery($user)
            ->with(['client:id,name']);

        $orders = $query
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get();

        $items = [];

        foreach ($orders as $order) {
            $checklist = $this->documentRequirementService->checklistForOrder($order);
            $pending = collect($checklist)->filter(fn (array $item): bool => ! ($item['completed'] ?? false));

            if ($pending->isEmpty()) {
                continue;
            }

            $items[] = [
                'order_id' => $order->id,
                'order_number' => $order->order_number ?: '#'.$order->id,
                'customer_id' => $order->customer_id ? (int) $order->customer_id : null,
                'customer_name' => $order->client?->name,
                'pending_count' => $pending->count(),
                'pending_labels' => $pending->take(3)->pluck('label')->filter()->values()->all(),
                'url' => route('orders.edit', $order, absolute: true).'?tab=documents',
            ];

            if (count($items) >= 15) {
                break;
            }
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderForMobileShell(Order $order): array
    {
        $status = $order->manual_status ?: $order->status;
        $checklist = $this->documentRequirementService->checklistForOrder($order);
        $pending = collect($checklist)->filter(fn (array $item): bool => ! ($item['completed'] ?? false));

        return [
            'id' => $order->id,
            'order_number' => $order->order_number ?: '#'.$order->id,
            'customer_id' => $order->customer_id ? (int) $order->customer_id : null,
            'customer_name' => $order->client?->name,
            'carrier_name' => $order->carrier?->name,
            'status' => $status,
            'loading_date' => $order->loading_date,
            'unloading_date' => $order->unloading_date,
            'updated_at' => optional($order->updated_at)?->toIso8601String(),
            'url' => route('orders.edit', $order, absolute: true),
            'documents_url' => route('orders.edit', $order, absolute: true).'?tab=documents',
            'documents_pending_count' => $pending->count(),
            'documents_total_count' => count($checklist),
            'documents_pending_labels' => $pending->take(3)->pluck('label')->filter()->values()->all(),
        ];
    }

    private function buildVisibleOrdersQuery(User $user): Builder
    {
        $query = Order::query();

        if (! Schema::hasTable('orders')
            || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders')) {
            return $query->whereRaw('1 = 0');
        }

        OrderViewAuthorization::applyOrdersVisibilityScope($query, $user, 'orders');

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('orders', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query;
    }
}
