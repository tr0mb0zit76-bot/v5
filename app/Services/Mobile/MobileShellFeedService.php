<?php

namespace App\Services\Mobile;

use App\Models\Contractor;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Services\MessengerService;
use App\Services\OrderDocumentRequirementService;
use App\Services\TaskSlaService;
use App\Support\LeadStatus;
use App\Support\OrderDocumentAccessAuthorization;
use App\Support\RoleAccess;
use App\Support\TaskStatus;
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

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'tasks');
        $needle = trim((string) $search);

        $query = Task::query()
            ->with(['responsible:id,name', 'lead:id,number', 'contractor:id,name'])
            ->where('status', '!=', 'done')
            ->when(
                ! $user->isAdmin() && $scope !== 'all',
                fn ($builder) => $builder->where('responsible_id', $user->id),
            );

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

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');
        $needle = trim((string) $search);

        $query = Order::query()
            ->with(['client:id,name', 'carrier:id,name'])
            ->when(
                ! $user->isAdmin() && $scope !== 'all',
                fn ($builder) => $builder->where('manager_id', $user->id),
            );

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('orders', 'is_active')) {
            $query->where('is_active', true);
        }

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

        $items = $orders->map(function (Order $order): array {
            $status = $order->manual_status ?: $order->status;
            $checklist = $this->documentRequirementService->checklistForOrder($order);
            $pending = collect($checklist)->filter(fn (array $item): bool => ! ($item['completed'] ?? false));

            return [
                'id' => $order->id,
                'order_number' => $order->order_number ?: '#'.$order->id,
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
        })->values()->all();

        return ['orders' => $items];
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

        return [
            'lead' => [
                'id' => (int) $lead->id,
                'number' => $lead->number,
                'title' => $lead->title,
                'status' => $lead->status,
                'status_label' => LeadStatus::label((string) $lead->status),
                'counterparty_name' => $lead->counterparty?->name,
                'responsible_name' => $lead->responsible?->name,
                'loading_location' => $lead->loading_location,
                'unloading_location' => $lead->unloading_location,
            ],
            'urls' => [
                'lead' => route('leads.show', $lead, absolute: true),
            ],
        ];
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

        if ($user->isAdmin()) {
            return true;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'leads');

        if ($scope === 'all') {
            return true;
        }

        return (int) $lead->responsible_id === (int) $user->id;
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

        if ($user->isAdmin()) {
            return true;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'tasks');

        if ($scope === 'all') {
            return true;
        }

        return (int) $task->responsible_id === (int) $user->id;
    }

    private function userCanViewOrder(User $user, Order $order): bool
    {
        if (! Schema::hasTable('orders') || ! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');

        if ($scope === 'all') {
            return true;
        }

        return (int) $order->manager_id === (int) $user->id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attentionOrdersForUser(User $user): array
    {
        if (! RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'orders')) {
            return [];
        }

        $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');

        $query = Order::query()
            ->with(['client:id,name'])
            ->when(
                ! $user->isAdmin() && $scope !== 'all',
                fn ($builder) => $builder->where('manager_id', $user->id),
            );

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('orders', 'is_active')) {
            $query->where('is_active', true);
        }

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
}
