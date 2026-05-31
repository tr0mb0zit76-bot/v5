<?php

namespace App\Services\Agents;

use App\Agents\AgentToolDefinition;
use App\Models\User;
use App\Services\Mcp\AiToolAuditLogger;
use App\Services\Mcp\ContractorMcpService;
use App\Services\Mcp\DispositionMcpService;
use App\Services\Mcp\McpAccessGate;
use App\Services\Mcp\OrderDocumentMcpService;
use App\Services\Mcp\OrderMcpService;
use App\Services\Mcp\SalesBookMcpService;
use App\Services\Mcp\TaskMcpService;
use App\Services\OrderActivityTimelineService;
use App\Support\DispositionSlot;
use App\Support\RoleAccess;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

class AgentToolRegistry
{
    /** @var list<AgentToolDefinition>|null */
    private ?array $definitions = null;

    public function __construct(
        private readonly McpAccessGate $access,
        private readonly AiToolAuditLogger $audit,
        private readonly OrderMcpService $orders,
        private readonly ContractorMcpService $contractors,
        private readonly TaskMcpService $tasks,
        private readonly OrderDocumentMcpService $orderDocuments,
        private readonly SalesBookMcpService $salesBook,
        private readonly DispositionMcpService $disposition,
        private readonly OrderActivityTimelineService $orderTimeline,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function openAiToolsFor(User $user): array
    {
        $tools = [];

        foreach ($this->definitions() as $definition) {
            if (($definition->canUse)($user)) {
                $tools[] = $definition->openAiDefinition();
            }
        }

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function invoke(User $user, string $name, array $arguments): array
    {
        foreach ($this->definitions() as $definition) {
            if ($definition->name !== $name) {
                continue;
            }

            if (! ($definition->canUse)($user)) {
                return ['error' => 'Нет доступа к инструменту '.$name.'.'];
            }

            try {
                $result = ($definition->invoke)($user, $arguments);
                $this->audit->log($user, $name, $arguments, true);

                return $result;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();
                $error = is_string($message) ? $message : 'Ошибка валидации.';
                $this->audit->log($user, $name, $arguments, false, $error);

                return ['error' => $error];
            } catch (AuthenticationException|ModelNotFoundException $exception) {
                $this->audit->log($user, $name, $arguments, false, $exception->getMessage());

                return ['error' => $exception->getMessage()];
            } catch (Throwable $throwable) {
                $this->audit->log($user, $name, $arguments, false, $throwable->getMessage());

                return ['error' => $throwable->getMessage()];
            }
        }

        return ['error' => 'Неизвестный инструмент: '.$name.'.'];
    }

    /**
     * @return list<AgentToolDefinition>
     */
    private function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $emptyObject = [
            'type' => 'object',
            'properties' => (object) [],
            'additionalProperties' => false,
        ];

        $this->definitions = [
            new AgentToolDefinition(
                name: 'get_user_context',
                description: 'Контекст текущего пользователя CRM: роль, области видимости, scope заказов.',
                parameters: $emptyObject,
                canUse: fn (User $user): bool => true,
                invoke: function (User $user): array {
                    return [
                        'user' => [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'is_admin' => $user->isAdmin(),
                        ],
                        'visibility_areas' => RoleAccess::userVisibilityAreas($user),
                        'orders_scope' => RoleAccess::resolveVisibilityScopeForUser($user, 'orders'),
                        'tasks_scope' => RoleAccess::resolveVisibilityScopeForUser($user, 'tasks'),
                        'can_view_finance' => $this->access->canViewFinance($user),
                    ];
                },
            ),
            new AgentToolDefinition(
                name: 'search_orders',
                description: 'Поиск заказов по номеру, id или фрагменту. Краткий список доступных заказов.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Номер, id или фрагмент. Пусто — последние в лимите.'],
                        'limit' => ['type' => 'integer', 'description' => '1–25', 'minimum' => 1, 'maximum' => 25],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: fn (User $user, array $args): array => $this->orders->search(
                    $user,
                    (string) ($args['query'] ?? ''),
                    (int) ($args['limit'] ?? 15),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_order',
                description: 'Карточка заказа по id.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'description' => 'ID заказа', 'minimum' => 1],
                    ],
                    'required' => ['order_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: function (User $user, array $args): array {
                    return ['order' => $this->orders->get($user, (int) $args['order_id'])];
                },
            ),
            new AgentToolDefinition(
                name: 'get_order_timeline',
                description: 'Лента активности заказа: статусы, задачи, документы, комментарии диспозиции.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'required' => ['order_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: function (User $user, array $args): array {
                    $order = $this->access->findAccessibleOrder($user, (int) $args['order_id']);

                    return [
                        'order_id' => $order->id,
                        'events' => $this->orderTimeline->timelineForOrder(
                            $order,
                            (int) ($args['limit'] ?? 30),
                        ),
                    ];
                },
            ),
            new AgentToolDefinition(
                name: 'list_order_documents',
                description: 'Документы, прикреплённые к заказу.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['order_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canDocuments($user),
                invoke: function (User $user, array $args): array {
                    return $this->orderDocuments->listForOrder($user, (int) $args['order_id']);
                },
            ),
            new AgentToolDefinition(
                name: 'search_contractors',
                description: 'Поиск контрагентов по названию или ИНН.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canContractors($user),
                invoke: fn (User $user, array $args): array => $this->contractors->search(
                    $user,
                    (string) ($args['query'] ?? ''),
                    (int) ($args['limit'] ?? 15),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_contractor',
                description: 'Карточка контрагента по id.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'contractor_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['contractor_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canContractors($user),
                invoke: function (User $user, array $args): array {
                    return ['contractor' => $this->contractors->get($user, (int) $args['contractor_id'])];
                },
            ),
            new AgentToolDefinition(
                name: 'search_tasks',
                description: 'Поиск задач по заголовку, номеру или id.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canTasks($user),
                invoke: fn (User $user, array $args): array => $this->tasks->search(
                    $user,
                    (string) ($args['query'] ?? ''),
                    (int) ($args['limit'] ?? 15),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_task',
                description: 'Карточка задачи по id.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'task_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['task_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canTasks($user),
                invoke: function (User $user, array $args): array {
                    return ['task' => $this->tasks->get($user, (int) $args['task_id'])];
                },
            ),
            new AgentToolDefinition(
                name: 'create_task',
                description: 'Создать задачу. При scope «только свои» ответственным может быть только текущий пользователь.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string'],
                        'responsible_id' => ['type' => 'integer', 'minimum' => 1],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                        'description' => ['type' => 'string'],
                        'due_at' => ['type' => 'string', 'description' => 'Y-m-d или ISO datetime'],
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                        'lead_id' => ['type' => 'integer', 'minimum' => 1],
                        'contractor_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['title', 'responsible_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canTasks($user),
                invoke: fn (User $user, array $args): array => $this->tasks->create($user, [
                    'title' => (string) ($args['title'] ?? ''),
                    'responsible_id' => (int) ($args['responsible_id'] ?? 0),
                    'priority' => (string) ($args['priority'] ?? 'medium'),
                    'description' => $args['description'] ?? null,
                    'due_at' => $args['due_at'] ?? null,
                    'order_id' => $args['order_id'] ?? null,
                    'lead_id' => $args['lead_id'] ?? null,
                    'contractor_id' => $args['contractor_id'] ?? null,
                ]),
            ),
            new AgentToolDefinition(
                name: 'upsert_disposition_entry',
                description: 'Записать ячейку диспозиции (утро/вечер: место и/или комментарий) для заказа «в пути».',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                        'date' => ['type' => 'string', 'description' => 'Y-m-d'],
                        'slot' => ['type' => 'string', 'enum' => DispositionSlot::values()],
                        'location' => ['type' => 'string'],
                        'comment' => ['type' => 'string'],
                    ],
                    'required' => ['order_id', 'date', 'slot'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user) && Schema::hasTable('disposition_entries'),
                invoke: function (User $user, array $args): array {
                    return $this->disposition->upsertEntry(
                        $user,
                        (int) $args['order_id'],
                        (string) $args['date'],
                        (string) $args['slot'],
                        array_key_exists('location', $args) ? (string) ($args['location'] ?? '') : null,
                        array_key_exists('comment', $args) ? (string) ($args['comment'] ?? '') : null,
                    );
                },
            ),
            new AgentToolDefinition(
                name: 'search_sales_book_articles',
                description: 'Поиск статей Книги продаж по заголовку.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::canReadSalesBook($user),
                invoke: fn (User $user, array $args): array => $this->salesBook->search(
                    $user,
                    (string) ($args['query'] ?? ''),
                    (int) ($args['limit'] ?? 20),
                ),
            ),
        ];

        return $this->definitions;
    }

    private function canOrders(User $user): bool
    {
        return RoleAccess::canAccessVisibilityArea($user, 'orders');
    }

    private function canTasks(User $user): bool
    {
        return RoleAccess::canAccessVisibilityArea($user, 'tasks') && Schema::hasTable('tasks');
    }

    private function canContractors(User $user): bool
    {
        return RoleAccess::canAccessVisibilityArea($user, 'contractors');
    }

    private function canDocuments(User $user): bool
    {
        return RoleAccess::canAccessVisibilityArea($user, 'documents');
    }
}
