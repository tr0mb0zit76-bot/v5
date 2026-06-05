<?php

namespace App\Services\Agents;

use App\Agents\AgentToolDefinition;
use App\Models\User;
use App\Services\Ai\AiUsageAnalyticsService;
use App\Services\Commercial\ManagerSalesCoachingInsightsService;
use App\Services\Mcp\AiToolAuditLogger;
use App\Services\Mcp\ContractorMcpService;
use App\Services\Mcp\DispositionMcpService;
use App\Services\Mcp\FleetMcpService;
use App\Services\Mcp\MailMcpService;
use App\Services\Mcp\McpAccessGate;
use App\Services\Mcp\OrderDocumentMcpService;
use App\Services\Mcp\OrderIntakeMcpService;
use App\Services\Mcp\OrderMcpService;
use App\Services\Mcp\SalesBookMcpService;
use App\Services\Mcp\TaskMcpService;
use App\Services\OrderActivityTimelineService;
use App\Services\SalesBook\SalesBookQualityInsightsService;
use App\Services\SalesBook\SalesBookQuizInsightsService;
use App\Services\SalesScripts\TrainerCoachingInsightsService;
use App\Support\AiInteractionFeature;
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
        private readonly FleetMcpService $fleet,
        private readonly TaskMcpService $tasks,
        private readonly OrderDocumentMcpService $orderDocuments,
        private readonly SalesBookMcpService $salesBook,
        private readonly SalesBookQualityInsightsService $salesBookQualityInsights,
        private readonly SalesBookQuizInsightsService $salesBookQuizInsights,
        private readonly DispositionMcpService $disposition,
        private readonly OrderActivityTimelineService $orderTimeline,
        private readonly AiUsageAnalyticsService $aiUsageAnalytics,
        private readonly TrainerCoachingInsightsService $trainerCoachingInsights,
        private readonly ManagerSalesCoachingInsightsService $managerSalesCoachingInsights,
        private readonly OrderIntakeMcpService $orderIntake,
        private readonly MailMcpService $mail,
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
                $this->audit->log($user, $name, $arguments, true, null, AiInteractionFeature::CommandBar);

                return $result;
            } catch (ValidationException $exception) {
                $message = collect($exception->errors())->flatten()->first();
                $error = is_string($message) ? $message : 'Ошибка валидации.';
                $this->audit->log($user, $name, $arguments, false, $error, AiInteractionFeature::CommandBar);

                return ['error' => $error];
            } catch (AuthenticationException|ModelNotFoundException $exception) {
                $this->audit->log($user, $name, $arguments, false, $exception->getMessage(), AiInteractionFeature::CommandBar);

                return ['error' => $exception->getMessage()];
            } catch (Throwable $throwable) {
                $this->audit->log($user, $name, $arguments, false, $throwable->getMessage(), AiInteractionFeature::CommandBar);

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
                description: 'Поиск заказов: номер (EXWL-1), id, номер заявки заказчика, название клиента или перевозчика (фрагмент «Эксвилл»).',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string', 'description' => 'Номер, id, имя клиента/перевозчика. Пусто — последние в лимите.'],
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
                description: 'Карточка заказа по id. В ответе loading_actual / unloading_actual — фактические даты погрузки и выгрузки.',
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
                name: 'get_order_field_lexicon',
                description: 'Словарь полей заказа: русские названия, синонимы («груз забрали» → loading_actual) и какой tool вызывать.',
                parameters: $emptyObject,
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: fn (User $user): array => $this->orders->fieldLexicon(),
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
                name: 'create_contractor',
                description: 'Создать контрагента. Минимум type и name; при полном ИНН без названия — автозаполнение из DaData. Владелец — текущий пользователь.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string', 'enum' => ['customer', 'carrier', 'contractor', 'both'], 'description' => 'По умолчанию customer.'],
                        'name' => ['type' => 'string', 'description' => 'Краткое название. Можно опустить при полном ИНН.'],
                        'inn' => ['type' => 'string'],
                        'kpp' => ['type' => 'string'],
                        'ogrn' => ['type' => 'string'],
                        'okpo' => ['type' => 'string'],
                        'legal_form' => ['type' => 'string', 'enum' => ['ooo', 'zao', 'ao', 'ip', 'samozanyaty', 'other']],
                        'full_name' => ['type' => 'string'],
                        'legal_address' => ['type' => 'string'],
                        'actual_address' => ['type' => 'string'],
                        'phone' => ['type' => 'string'],
                        'email' => ['type' => 'string', 'format' => 'email'],
                        'contact_person' => ['type' => 'string'],
                        'autofill_from_inn' => ['type' => 'boolean', 'description' => 'По умолчанию true.'],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canContractors($user),
                invoke: fn (User $user, array $args): array => $this->contractors->create($user, $args),
            ),
            new AgentToolDefinition(
                name: 'create_fleet_driver',
                description: 'Создать водителя (модалка «Водитель»): carrier_contractor_id перевозчика и full_name обязательны.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'carrier_contractor_id' => ['type' => 'integer', 'minimum' => 1],
                        'full_name' => ['type' => 'string'],
                        'passport_series' => ['type' => 'string'],
                        'passport_number' => ['type' => 'string'],
                        'passport_issued_by' => ['type' => 'string'],
                        'passport_issued_at' => ['type' => 'string', 'description' => 'Y-m-d'],
                        'phone' => ['type' => 'string'],
                        'license_number' => ['type' => 'string'],
                        'license_categories' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['carrier_contractor_id', 'full_name'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canDrivers($user),
                invoke: fn (User $user, array $args): array => $this->fleet->createDriver($user, [
                    'carrier_contractor_id' => (int) ($args['carrier_contractor_id'] ?? 0),
                    'full_name' => (string) ($args['full_name'] ?? ''),
                    'passport_series' => $args['passport_series'] ?? null,
                    'passport_number' => $args['passport_number'] ?? null,
                    'passport_issued_by' => $args['passport_issued_by'] ?? null,
                    'passport_issued_at' => $args['passport_issued_at'] ?? null,
                    'phone' => $args['phone'] ?? null,
                    'license_number' => $args['license_number'] ?? null,
                    'license_categories' => $args['license_categories'] ?? null,
                    'notes' => $args['notes'] ?? null,
                ]),
            ),
            new AgentToolDefinition(
                name: 'create_fleet_vehicle',
                description: 'Создать авто (модалка «Авто»): owner_contractor_id владельца ТС и хотя бы госномер или марка.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'owner_contractor_id' => ['type' => 'integer', 'minimum' => 1],
                        'tractor_brand' => ['type' => 'string'],
                        'trailer_brand' => ['type' => 'string'],
                        'tractor_plate' => ['type' => 'string'],
                        'trailer_plate' => ['type' => 'string'],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['owner_contractor_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canDrivers($user),
                invoke: fn (User $user, array $args): array => $this->fleet->createVehicle($user, [
                    'owner_contractor_id' => (int) ($args['owner_contractor_id'] ?? 0),
                    'tractor_brand' => $args['tractor_brand'] ?? null,
                    'trailer_brand' => $args['trailer_brand'] ?? null,
                    'tractor_plate' => $args['tractor_plate'] ?? null,
                    'trailer_plate' => $args['trailer_plate'] ?? null,
                    'notes' => $args['notes'] ?? null,
                ]),
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
                name: 'add_order_note',
                description: 'Добавить заметку в ленту активности заказа (не меняет поля карточки).',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                        'body' => ['type' => 'string', 'description' => 'Текст заметки'],
                        'title' => ['type' => 'string', 'description' => 'Заголовок в ленте, по умолчанию «Заметка»'],
                    ],
                    'required' => ['order_id', 'body'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: function (User $user, array $args): array {
                    return $this->orders->addNote(
                        $user,
                        (int) $args['order_id'],
                        (string) ($args['body'] ?? ''),
                        isset($args['title']) ? (string) $args['title'] : null,
                    );
                },
            ),
            new AgentToolDefinition(
                name: 'update_order_field',
                description: 'Изменить поле заказа из inline-грида (ставки, треки, order_date, статус). Не для фактической погрузки — для неё update_order_route_actual.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                        'field' => [
                            'type' => 'string',
                            'description' => 'Ключ или русское название/синоним (см. get_order_field_lexicon).',
                        ],
                        'value' => ['description' => 'Новое значение; даты dd.mm.yyyy или Y-m-d'],
                    ],
                    'required' => ['order_id', 'field'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: fn (User $user, array $args): array => $this->orders->updateField(
                    $user,
                    (int) $args['order_id'],
                    (string) ($args['field'] ?? ''),
                    $args['value'] ?? null,
                ),
            ),
            new AgentToolDefinition(
                name: 'update_order_route_actual',
                description: 'Фактическая дата погрузки (loading_actual) или выгрузки (unloading_actual). «Груз забрали» = loading_actual.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                        'kind' => ['type' => 'string', 'description' => 'loading_actual или unloading_actual (можно синоним из lexicon)'],
                        'date' => ['type' => 'string', 'description' => 'Y-m-d или dd.mm.yyyy'],
                        'leg_stage' => ['type' => 'string', 'description' => 'Плечо, по умолчанию leg_1'],
                    ],
                    'required' => ['order_id', 'kind', 'date'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: fn (User $user, array $args): array => $this->orders->updateRouteActual(
                    $user,
                    (int) $args['order_id'],
                    (string) ($args['kind'] ?? ''),
                    $args['date'] ?? null,
                    isset($args['leg_stage']) ? (string) $args['leg_stage'] : null,
                ),
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
                description: 'Поиск страниц Книги продаж по заголовку и тексту. Возвращает id, заголовок, excerpt при совпадении в тексте.',
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
            new AgentToolDefinition(
                name: 'get_sales_book_article',
                description: 'Полный текст страницы Книги продаж по id (markdown). Вызывай после search_sales_book_articles.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'article_id' => ['type' => 'integer', 'minimum' => 1],
                        'max_chars' => ['type' => 'integer', 'minimum' => 500, 'maximum' => 50000],
                    ],
                    'required' => ['article_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::canReadSalesBook($user),
                invoke: fn (User $user, array $args): array => $this->salesBook->get(
                    $user,
                    (int) $args['article_id'],
                    isset($args['max_chars']) ? (int) $args['max_chars'] : null,
                ),
            ),
            new AgentToolDefinition(
                name: 'upsert_sales_book_article',
                description: 'Создать или обновить дочернюю страницу Книги продаж под указанным родителем (по заголовку родителя).',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'parent_title' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'markdown_content' => ['type' => 'string'],
                        'sort_order' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 1000000],
                        'tags' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'maxItems' => 20,
                        ],
                    ],
                    'required' => ['parent_title', 'title', 'markdown_content'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::canWriteSalesBook($user),
                invoke: fn (User $user, array $args): array => $this->salesBook->upsertChildPage(
                    $user,
                    (string) $args['parent_title'],
                    (string) $args['title'],
                    (string) $args['markdown_content'],
                    isset($args['sort_order']) ? (int) $args['sort_order'] : null,
                    is_array($args['tags'] ?? null) ? $args['tags'] : [],
                ),
            ),
            new AgentToolDefinition(
                name: 'get_sales_book_quality_insights',
                description: 'Качество Книги продаж: проблемные статьи, свежие замечания, черновики и подсказки редактору.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::canWriteSalesBook($user),
                invoke: fn (User $user, array $args): array => $this->salesBookQualityInsights->insights(
                    (int) ($args['days'] ?? 30),
                    (int) ($args['limit'] ?? 10),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_sales_book_quiz_insights',
                description: 'Статистика тестов Книги продаж: попытки, средний балл, сводка по сотрудникам и статьям.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                        'article_id' => ['type' => 'integer', 'minimum' => 1],
                        'user_id' => ['type' => 'integer', 'minimum' => 1],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::canViewSalesBookQuizInsights($user),
                invoke: fn (User $user, array $args): array => $this->salesBookQuizInsights->insights(
                    (int) ($args['days'] ?? 30),
                    isset($args['article_id']) ? (int) $args['article_id'] : null,
                    RoleAccess::resolveSalesBookQuizInsightsUserId(
                        $user,
                        isset($args['user_id']) ? (int) $args['user_id'] : null,
                    ),
                    (int) ($args['limit'] ?? 20),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_ai_usage_insights',
                description: 'Аналитика обращений к AI: частые вопросы, слабые/неудачные ответы, использование tools. Только для администраторов и системных настроек.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                        'top_limit' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 50],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::canViewAiAnalytics($user),
                invoke: fn (User $user, array $args): array => $this->aiUsageAnalytics->insights(
                    (int) ($args['days'] ?? config('ai.analytics.insights_default_days', 30)),
                    (int) ($args['top_limit'] ?? 20),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_trainer_coaching_insights',
                description: 'Аналитика тренажёра: тупики, зацикливание диалогов, hotspots по профилям и сценариям, рекомендации по улучшению.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                        'user_id' => ['type' => 'integer', 'minimum' => 1],
                        'sample_limit' => ['type' => 'integer', 'minimum' => 5, 'maximum' => 50],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::canViewTrainerAnalytics($user)
                    || RoleAccess::canViewAiAnalytics($user),
                invoke: fn (User $user, array $args): array => $this->trainerCoachingInsights->insights(
                    $user,
                    (int) ($args['days'] ?? 30),
                    isset($args['user_id']) ? (int) $args['user_id'] : null,
                    (int) ($args['sample_limit'] ?? 15),
                ),
            ),
            new AgentToolDefinition(
                name: 'remember_order_intake_phrase',
                description: 'Запомнить формулировку пользователя для распознавания заявок после уточнения в диалоге (например «оплата через месяц» → «30 календарных дней»).',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'source_phrase' => ['type' => 'string', 'description' => 'Как сказал пользователь.'],
                        'canonical_value' => ['type' => 'string', 'description' => 'Как записать в CRM.'],
                        'field' => [
                            'type' => 'string',
                            'enum' => ['payment_terms', 'own_company', 'general'],
                            'description' => 'payment_terms | own_company | general',
                        ],
                    ],
                    'required' => ['source_phrase', 'canonical_value', 'field'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: fn (User $user, array $args): array => $this->orderIntake->rememberPhrase(
                    $user,
                    (string) ($args['source_phrase'] ?? ''),
                    (string) ($args['canonical_value'] ?? ''),
                    (string) ($args['field'] ?? 'general'),
                ),
            ),
            new AgentToolDefinition(
                name: 'create_order_intake_draft_from_text',
                description: 'Создать черновик заявки на заказ из полного текста (маршрут, груз, ставки, оплата, своя компания). Вызывай только когда данных достаточно или пользователь подтвердил уточнения.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'instruction' => ['type' => 'string', 'description' => 'Полный текст заявки от пользователя.'],
                    ],
                    'required' => ['instruction'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: fn (User $user, array $args): array => $this->orderIntake->createDraftFromText(
                    $user,
                    (string) ($args['instruction'] ?? ''),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_order_intake_draft',
                description: 'Черновик заявки по draft_id: wizard_patch, предупреждения, совпадения контрагентов.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'draft_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['draft_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: function (User $user, array $args): array {
                    return ['draft' => $this->orderIntake->getDraft($user, (int) $args['draft_id'])];
                },
            ),
            new AgentToolDefinition(
                name: 'list_order_intake_drafts',
                description: 'Последние черновики заявок текущего пользователя.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: fn (User $user, array $args): array => [
                    'drafts' => $this->orderIntake->listRecentDrafts($user, (int) ($args['limit'] ?? 10)),
                ],
            ),
            new AgentToolDefinition(
                name: 'search_mail_threads',
                description: 'Поиск переписки (IMAP sync): тема, текст, email контрагента.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 25],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canMail($user),
                invoke: fn (User $user, array $args): array => $this->mail->searchThreads(
                    $user,
                    (string) ($args['query'] ?? ''),
                    (int) ($args['limit'] ?? 15),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_mail_thread',
                description: 'Письма в цепочке по thread_id.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'thread_id' => ['type' => 'integer', 'minimum' => 1],
                        'message_limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'required' => ['thread_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canMail($user),
                invoke: fn (User $user, array $args): array => $this->mail->getThread(
                    $user,
                    (int) $args['thread_id'],
                    (int) ($args['message_limit'] ?? 20),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_mail_sync_status',
                description: 'Статус синхронизации почты: mail_last_sync_at, mail_last_sync_error, IMAP host.',
                parameters: $emptyObject,
                canUse: fn (User $user): bool => $this->canMail($user),
                invoke: fn (User $user): array => $this->mail->syncStatus($user),
            ),
            new AgentToolDefinition(
                name: 'send_mail',
                description: 'Отправить исходящее письмо из CRM (SMTP). Возвращает thread_id и message_id.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'subject' => ['type' => 'string', 'maxLength' => 255],
                        'body' => ['type' => 'string', 'maxLength' => 20000],
                        'to' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'format' => 'email'],
                            'minItems' => 1,
                        ],
                        'cc' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'format' => 'email'],
                        ],
                        'lead_id' => ['type' => 'integer', 'minimum' => 1],
                        'order_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['subject', 'body', 'to'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canMail($user),
                invoke: function (User $user, array $args): array {
                    return $this->mail->sendMail(
                        $user,
                        (string) $args['subject'],
                        (string) $args['body'],
                        $args['to'],
                        $args['cc'] ?? [],
                        isset($args['lead_id']) ? (int) $args['lead_id'] : null,
                        isset($args['order_id']) ? (int) $args['order_id'] : null,
                    );
                },
            ),
            new AgentToolDefinition(
                name: 'reply_mail_thread',
                description: 'Ответить в существующую цепочку писем (thread_id из search_mail_threads / get_mail_thread).',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'thread_id' => ['type' => 'integer', 'minimum' => 1],
                        'body' => ['type' => 'string', 'maxLength' => 20000],
                        'to' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'format' => 'email'],
                            'minItems' => 1,
                        ],
                        'cc' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'format' => 'email'],
                        ],
                    ],
                    'required' => ['thread_id', 'body'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canMail($user),
                invoke: function (User $user, array $args): array {
                    return $this->mail->replyToThread(
                        $user,
                        (int) $args['thread_id'],
                        (string) $args['body'],
                        $args['to'] ?? null,
                        $args['cc'] ?? [],
                    );
                },
            ),
            new AgentToolDefinition(
                name: 'get_manager_sales_coaching_insights',
                description: 'Outcome Intelligence: почему проваливаются/выигрываются лиды, гигиена сделки, простой vs активность на этапах, рекомендации.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 365],
                        'user_id' => ['type' => 'integer', 'minimum' => 1],
                        'sample_limit' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 25],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::canViewSalesCoachingInsights($user),
                invoke: fn (User $user, array $args): array => $this->managerSalesCoachingInsights->insights(
                    $user,
                    (int) ($args['days'] ?? config('outcome_intelligence.coaching_default_days', 90)),
                    isset($args['user_id']) ? (int) $args['user_id'] : null,
                    (int) ($args['sample_limit'] ?? config('outcome_intelligence.coaching_sample_limit', 10)),
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

    private function canMail(User $user): bool
    {
        return RoleAccess::canAccessVisibilityArea($user, 'mail');
    }

    private function canDrivers(User $user): bool
    {
        return RoleAccess::canAccessVisibilityArea($user, 'drivers');
    }
}
