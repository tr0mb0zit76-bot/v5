<?php

namespace App\Services\Mobile;

use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Services\MessengerService;
use App\Services\OrderDocumentRequirementService;
use App\Services\TaskSlaService;
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
