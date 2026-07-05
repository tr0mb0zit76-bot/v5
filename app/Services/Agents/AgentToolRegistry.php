<?php

namespace App\Services\Agents;

use App\Agents\AgentToolDefinition;
use App\Models\Contractor;
use App\Models\ContractorPrintFormChangeRequest;
use App\Models\User;
use App\Services\Ai\AiUsageAnalyticsService;
use App\Services\Commercial\HeadOfSalesInsightsService;
use App\Services\Commercial\MailThreadAnalysisService;
use App\Services\Commercial\ManagerSalesCoachingInsightsService;
use App\Services\Leads\LeadOperationalBriefService;
use App\Services\ManagementAccounting\ManagementAccountingInsightsService;
use App\Services\Mcp\AiToolAuditLogger;
use App\Services\Mcp\ContractorMcpService;
use App\Services\Mcp\DispositionMcpService;
use App\Services\Mcp\FleetMcpService;
use App\Services\Mcp\MailMcpService;
use App\Services\Mcp\ManagementAccountingMcpService;
use App\Services\Mcp\McpAccessGate;
use App\Services\Mcp\OrderDocumentMcpService;
use App\Services\Mcp\OrderIntakeMcpService;
use App\Services\Mcp\OrderMcpService;
use App\Services\Mcp\PrintFormTemplatesMcpService;
use App\Services\Mcp\SalesBookMcpService;
use App\Services\Mcp\TaskMcpService;
use App\Services\OrderActivityTimelineService;
use App\Services\PrintForm\ContractorPrintFormChangeRequestService;
use App\Services\SalesBook\SalesBookQualityInsightsService;
use App\Services\SalesBook\SalesBookQuizInsightsService;
use App\Services\SalesScripts\SalesScriptCoachingInsightsService;
use App\Services\SalesScripts\TrainerCoachingInsightsService;
use App\Support\AiInteractionFeature;
use App\Support\DispositionSlot;
use App\Support\RoleAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use RuntimeException;
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
        private readonly SalesScriptCoachingInsightsService $salesScriptCoachingInsights,
        private readonly ManagerSalesCoachingInsightsService $managerSalesCoachingInsights,
        private readonly HeadOfSalesInsightsService $headOfSalesInsights,
        private readonly OrderIntakeMcpService $orderIntake,
        private readonly MailMcpService $mail,
        private readonly MailThreadAnalysisService $mailAnalysis,
        private readonly PrintFormTemplatesMcpService $printFormTemplates,
        private readonly ContractorPrintFormChangeRequestService $printFormChanges,
        private readonly ManagementAccountingMcpService $managementAccounting,
        private readonly ManagementAccountingInsightsService $managementAccountingInsights,
        private readonly LeadOperationalBriefService $leadOperationalBrief,
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
            } catch (AuthorizationException|AuthenticationException|ModelNotFoundException $exception) {
                $this->audit->log($user, $name, $arguments, false, $exception->getMessage(), AiInteractionFeature::CommandBar);

                return ['error' => $exception->getMessage()];
            } catch (RuntimeException $exception) {
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
                        'can_management_accounting' => RoleAccess::canAccessManagementAccounting($user),
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
                description: 'Поиск задач: заголовок, номер, id или имя ответственного (фрагмент «Тищенко», «Дина»).',
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
                        'create_parent_if_missing' => ['type' => 'boolean'],
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
                    (bool) ($args['create_parent_if_missing'] ?? false),
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
                name: 'get_sales_script_coaching_insights',
                description: 'Аналитика живых прохождений скриптов: исходы, возражения, слабые менеджеры, проблемные сценарии и рекомендации руководителю.',
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
                invoke: fn (User $user, array $args): array => $this->salesScriptCoachingInsights->insights(
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
                name: 'extract_order_draft_from_document',
                description: 'Черновик заявки из файла (PDF/DOCX/скан): file_name + content_base64.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'file_name' => ['type' => 'string'],
                        'content_base64' => ['type' => 'string'],
                        'mime_type' => ['type' => 'string'],
                    ],
                    'required' => ['file_name', 'content_base64'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: fn (User $user, array $args): array => $this->orderIntake->extractDraftFromDocument(
                    $user,
                    (string) ($args['file_name'] ?? ''),
                    (string) ($args['content_base64'] ?? ''),
                    (string) ($args['mime_type'] ?? 'application/octet-stream'),
                ),
            ),
            new AgentToolDefinition(
                name: 'apply_order_wizard_draft',
                description: 'Создать заказ из draft_id. dry_run=true → preview + confirm_token; затем confirm_token без dry_run.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'draft_id' => ['type' => 'integer', 'minimum' => 1],
                        'dry_run' => ['type' => 'boolean'],
                        'confirm_token' => ['type' => 'string'],
                    ],
                    'required' => ['draft_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canOrders($user),
                invoke: fn (User $user, array $args): array => $this->orderIntake->applyWizardDraft(
                    $user,
                    (int) ($args['draft_id'] ?? 0),
                    (bool) ($args['dry_run'] ?? false),
                    isset($args['confirm_token']) ? (string) $args['confirm_token'] : null,
                ),
            ),
            new AgentToolDefinition(
                name: 'search_mail_threads',
                description: 'Поиск переписки (IMAP sync): тема, текст, email. «Письма у Садыкова» → mailbox_owner или фамилия в query (admin). В ответе mailbox_total_threads.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'query' => ['type' => 'string'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                        'mailbox_user_id' => ['type' => 'integer', 'minimum' => 1],
                        'mailbox_owner' => ['type' => 'string', 'maxLength' => 120],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canMail($user),
                invoke: fn (User $user, array $args): array => $this->mail->searchThreads(
                    $user,
                    (string) ($args['query'] ?? ''),
                    (int) ($args['limit'] ?? 15),
                    isset($args['mailbox_user_id']) ? (int) $args['mailbox_user_id'] : null,
                    isset($args['mailbox_owner']) ? (string) $args['mailbox_owner'] : null,
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
                description: 'Статус синхронизации почты: mail_last_sync_at, mail_last_sync_error, IMAP host, thread_count по сотрудникам.',
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
                name: 'summarize_mail_thread',
                description: 'Краткое резюме переписки по thread_id: суть, ключевые пункты, открытые вопросы, участники. Без автосend.',
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
                invoke: fn (User $user, array $args): array => $this->mailAnalysis->summarizeThread(
                    $user,
                    (int) $args['thread_id'],
                    (int) ($args['message_limit'] ?? 20),
                ),
            ),
            new AgentToolDefinition(
                name: 'draft_mail_reply',
                description: 'Черновик ответа в цепочку (thread_id + tone). Не отправляет письмо — только subject/body для проверки.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'thread_id' => ['type' => 'integer', 'minimum' => 1],
                        'tone' => ['type' => 'string', 'enum' => ['neutral', 'friendly', 'formal', 'assertive']],
                        'message_limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'required' => ['thread_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canMail($user),
                invoke: fn (User $user, array $args): array => $this->mailAnalysis->draftReply(
                    $user,
                    (int) $args['thread_id'],
                    (string) ($args['tone'] ?? 'neutral'),
                    (int) ($args['message_limit'] ?? 20),
                ),
            ),
            new AgentToolDefinition(
                name: 'suggest_lead_next_step_from_mail',
                description: 'Следующий шаг по лиду с учётом переписки (lead_id; thread_id опционален). Рекомендация, без автосоздания задачи.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'lead_id' => ['type' => 'integer', 'minimum' => 1],
                        'thread_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['lead_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canMail($user) && RoleAccess::canAccessVisibilityArea($user, 'leads'),
                invoke: fn (User $user, array $args): array => $this->mailAnalysis->suggestLeadNextStep(
                    $user,
                    (int) $args['lead_id'],
                    isset($args['thread_id']) ? (int) $args['thread_id'] : null,
                ),
            ),
            new AgentToolDefinition(
                name: 'get_lead_operational_brief',
                description: 'Операционный бриф лида: что сделать сейчас, пробелы в данных, риски (просрочка SLA, простой), готовность к переходу. Для разбора «почему застрял» — сначала этот tool, не копай БД вручную.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'lead_id' => ['type' => 'integer', 'minimum' => 1],
                        'lead_ids' => [
                            'type' => 'array',
                            'items' => ['type' => 'integer', 'minimum' => 1],
                            'maxItems' => 25,
                        ],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::hasVisibilityArea(RoleAccess::userVisibilityAreas($user), 'leads'),
                invoke: function (User $user, array $args): array {
                    if (isset($args['lead_ids']) && is_array($args['lead_ids'])) {
                        $ids = array_map(fn (mixed $id): int => (int) $id, $args['lead_ids']);

                        return [
                            'briefs' => $this->leadOperationalBrief->buildManyForUser($user, $ids),
                        ];
                    }

                    if (! isset($args['lead_id'])) {
                        return ['error' => 'Укажите lead_id или lead_ids.'];
                    }

                    return [
                        'brief' => $this->leadOperationalBrief->buildForUser($user, (int) $args['lead_id']),
                    ];
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
            new AgentToolDefinition(
                name: 'get_head_of_sales_insights',
                description: 'Сводка для руководителя продаж: маржа по менеджерам, воронка, скрипты, риски открытых лидов, transport mix, приоритетные действия.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'days' => ['type' => 'integer', 'minimum' => 7, 'maximum' => 365],
                        'user_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => RoleAccess::canViewHeadOfSalesInsights($user),
                invoke: fn (User $user, array $args): array => $this->headOfSalesInsights->insights(
                    $user,
                    (int) ($args['days'] ?? 90),
                    isset($args['user_id']) ? (int) $args['user_id'] : null,
                ),
            ),
            new AgentToolDefinition(
                name: 'get_print_form_basic_terms',
                description: 'Прочитать общие базовые условия cp/dp (Настройки → Базовые условия для договоров-заявок). party=carrier|customer; без party — обе стороны. Перед зеркалированием норм перевозчика в заказчика — сначала этот tool.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'party' => ['type' => 'string', 'enum' => ['customer', 'carrier']],
                        'contractor_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->printFormChanges->canDirectManagePrintForm($user),
                invoke: fn (User $user, array $args): array => $this->printFormTemplates->readBasicTerms(
                    isset($args['party']) ? (string) $args['party'] : null,
                    isset($args['contractor_id']) ? (int) $args['contractor_id'] : null,
                ),
            ),
            new AgentToolDefinition(
                name: 'get_print_form_templates_insights',
                description: 'Шаблоны DOCX, диагностика печати и снимок basic_terms. Без code/query — список шаблонов и общие условия обеих сторон. Для только пунктов условий удобнее get_print_form_basic_terms.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'code' => ['type' => 'string', 'description' => 'Точный код шаблона.'],
                        'query' => ['type' => 'string', 'description' => 'Поиск по коду или названию.'],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->printFormChanges->canDirectManagePrintForm($user),
                invoke: fn (User $user, array $args): array => $this->printFormTemplates->insights(
                    isset($args['code']) ? (string) $args['code'] : null,
                    isset($args['query']) ? (string) $args['query'] : null,
                    (int) ($args['limit'] ?? 20),
                ),
            ),
            new AgentToolDefinition(
                name: 'upsert_print_form_basic_terms',
                description: 'Сохранить базовые условия cp/dp (глобальные или для контрагента). party customer|carrier, items — массив строк пунктов. Точка в начале пункта («. Текст») — часть текста, если нужна в печати. Прямая запись — admin/settings_system.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'party' => ['type' => 'string', 'enum' => ['customer', 'carrier']],
                        'contractor_id' => ['type' => 'integer', 'minimum' => 1],
                        'items' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                        ],
                    ],
                    'required' => ['party', 'items'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->printFormChanges->canDirectManagePrintForm($user),
                invoke: function (User $user, array $args): array {
                    return $this->printFormTemplates->upsertBasicTerms(
                        (string) ($args['party'] ?? ''),
                        isset($args['contractor_id']) ? (int) $args['contractor_id'] : null,
                        is_array($args['items'] ?? null) ? $args['items'] : [],
                    );
                },
            ),
            new AgentToolDefinition(
                name: 'submit_contractor_print_form_change',
                description: 'Отправить базовые условия контрагента на согласование руководителю (задача + уведомление).',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'contractor_id' => ['type' => 'integer', 'minimum' => 1],
                        'party' => ['type' => 'string', 'enum' => ['customer', 'carrier']],
                        'items' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'minItems' => 1,
                        ],
                        'manager_notes' => ['type' => 'string', 'maxLength' => 5000],
                        'yurik_summary' => ['type' => 'string', 'maxLength' => 10000],
                    ],
                    'required' => ['contractor_id', 'party', 'items'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canContractors($user),
                invoke: function (User $user, array $args): array {
                    $contractor = Contractor::query()->find((int) ($args['contractor_id'] ?? 0));

                    if ($contractor === null) {
                        throw new ModelNotFoundException('Контрагент не найден.');
                    }

                    $change = $this->printFormChanges->submitBasicTermsChange(
                        $contractor,
                        (string) ($args['party'] ?? ''),
                        is_array($args['items'] ?? null) ? $args['items'] : [],
                        $user,
                        isset($args['manager_notes']) ? (string) $args['manager_notes'] : null,
                        isset($args['yurik_summary']) ? (string) $args['yurik_summary'] : null,
                    );

                    return ['change_request' => $this->printFormChanges->serializeRequest($change)];
                },
            ),
            new AgentToolDefinition(
                name: 'resolve_contractor_print_form_change',
                description: 'Утвердить, отклонить или вернуть на согласование с контрагентом заявку на изменение базовых условий.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'change_request_id' => ['type' => 'integer', 'minimum' => 1],
                        'action' => ['type' => 'string', 'enum' => ['approve', 'reject', 'needs_counterparty']],
                        'reason' => ['type' => 'string', 'maxLength' => 2000],
                        'notes' => ['type' => 'string', 'maxLength' => 2000],
                    ],
                    'required' => ['change_request_id', 'action'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->printFormChanges->canApprovePrintFormChanges($user),
                invoke: function (User $user, array $args): array {
                    $changeRequest = ContractorPrintFormChangeRequest::query()->find(
                        (int) ($args['change_request_id'] ?? 0),
                    );

                    if ($changeRequest === null) {
                        throw new ModelNotFoundException('Заявка не найдена.');
                    }

                    $action = (string) ($args['action'] ?? '');

                    if ($action === 'approve') {
                        $changeRequest = $this->printFormChanges->approve($changeRequest, $user);
                    } elseif ($action === 'reject') {
                        $changeRequest = $this->printFormChanges->reject(
                            $changeRequest,
                            $user,
                            (string) ($args['reason'] ?? 'Без комментария'),
                        );
                    } else {
                        $changeRequest = $this->printFormChanges->markNeedsCounterparty(
                            $changeRequest,
                            $user,
                            isset($args['notes']) ? (string) $args['notes'] : null,
                        );
                    }

                    return ['change_request' => $this->printFormChanges->serializeRequest($changeRequest)];
                },
            ),
            new AgentToolDefinition(
                name: 'get_management_accounting_insights',
                description: 'CFO-аналитика управленки: KPI, тренды, структура расходов, план/факт, риски выписки, рекомендации.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'period_type' => ['type' => 'string', 'enum' => ['month', 'quarter', 'year']],
                        'period_anchor' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                        'watchlist_limit' => ['type' => 'integer', 'minimum' => 3, 'maximum' => 15],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canManagementAccounting($user),
                invoke: fn (User $user, array $args): array => $this->managementAccountingInsights->insights(
                    $user,
                    (string) ($args['period_type'] ?? 'month'),
                    isset($args['period_anchor']) ? (string) $args['period_anchor'] : null,
                    (int) ($args['watchlist_limit'] ?? 8),
                ),
            ),
            new AgentToolDefinition(
                name: 'get_management_accounting_analytics',
                description: 'Полная аналитика управленки за период: totals, pivot, статьи, план/факт.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'period_type' => ['type' => 'string', 'enum' => ['month', 'quarter', 'year']],
                        'period_anchor' => ['type' => 'string', 'description' => 'YYYY-MM-DD'],
                    ],
                    'required' => ['period_type'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canManagementAccounting($user),
                invoke: fn (User $user, array $args): array => [
                    'analytics' => $this->managementAccounting->analytics(
                        $user,
                        (string) ($args['period_type'] ?? 'month'),
                        $args['period_anchor'] ?? null,
                    ),
                ],
            ),
            new AgentToolDefinition(
                name: 'list_management_statement_imports',
                description: 'Список импортов банковских выписок (управленка).',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canManagementAccounting($user),
                invoke: fn (User $user, array $args): array => [
                    'imports' => $this->managementAccounting->listImports($user, (int) ($args['limit'] ?? 20)),
                ],
            ),
            new AgentToolDefinition(
                name: 'list_management_statement_lines',
                description: 'Строки выписки по import_id; status=pending для неразнесённых.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'import_id' => ['type' => 'integer', 'minimum' => 1],
                        'status' => ['type' => 'string', 'enum' => ['pending', 'allocated']],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    ],
                    'required' => ['import_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canManagementAccounting($user),
                invoke: fn (User $user, array $args): array => [
                    'lines' => $this->managementAccounting->listLines(
                        $user,
                        (int) $args['import_id'],
                        isset($args['status']) ? (string) $args['status'] : null,
                        (int) ($args['limit'] ?? 50),
                    ),
                ],
            ),
            new AgentToolDefinition(
                name: 'suggest_management_statement_line',
                description: 'Подсказка разнесения строки выписки (правила, заказ, статья).',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'line_id' => ['type' => 'integer', 'minimum' => 1],
                    ],
                    'required' => ['line_id'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canManagementAccounting($user),
                invoke: fn (User $user, array $args): array => $this->managementAccounting->suggestLine(
                    $user,
                    (int) $args['line_id'],
                ),
            ),
            new AgentToolDefinition(
                name: 'allocate_management_statement_line',
                description: 'Разнести строку выписки. Только по явной просьбе пользователя.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'line_id' => ['type' => 'integer', 'minimum' => 1],
                        'allocation_type' => ['type' => 'string', 'enum' => ['operational', 'payroll', 'category']],
                        'category_id' => ['type' => 'integer', 'minimum' => 1],
                        'payment_schedule_id' => ['type' => 'integer', 'minimum' => 1],
                        'user_id' => ['type' => 'integer', 'minimum' => 1],
                        'amount' => ['type' => 'number', 'minimum' => 0.01],
                        'notes' => ['type' => 'string', 'maxLength' => 500],
                        'remember_keyword' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 128],
                        'remember_notes' => ['type' => 'string', 'maxLength' => 255],
                    ],
                    'required' => ['line_id', 'allocation_type'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canManagementAccounting($user),
                invoke: fn (User $user, array $args): array => $this->managementAccounting->allocateLine(
                    $user,
                    (int) $args['line_id'],
                    $args,
                ),
            ),
            new AgentToolDefinition(
                name: 'list_management_expense_categories',
                description: 'Справочник статей управленческого учёта.',
                parameters: $emptyObject,
                canUse: fn (User $user): bool => $this->canManagementAccounting($user),
                invoke: fn (User $user): array => [
                    'categories' => $this->managementAccounting->listCategories($user),
                ],
            ),
            new AgentToolDefinition(
                name: 'list_management_reconcile_rules',
                description: 'Активные правила автоподбора при разнесении выписки.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
                    ],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canManagementAccounting($user),
                invoke: fn (User $user, array $args): array => [
                    'rules' => $this->managementAccounting->listRules($user, (int) ($args['limit'] ?? 30)),
                ],
            ),
            new AgentToolDefinition(
                name: 'remember_management_reconcile_rule',
                description: 'Создать правило разнесения по ключевому слову. Только по явной просьбе.',
                parameters: [
                    'type' => 'object',
                    'properties' => [
                        'keyword' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 128],
                        'allocation_type' => ['type' => 'string', 'enum' => ['operational', 'payroll', 'category']],
                        'category_id' => ['type' => 'integer', 'minimum' => 1],
                        'user_id' => ['type' => 'integer', 'minimum' => 1],
                        'order_number' => ['type' => 'string'],
                        'payment_schedule_id' => ['type' => 'integer', 'minimum' => 1],
                        'direction' => ['type' => 'string', 'enum' => ['in', 'out']],
                        'notes' => ['type' => 'string', 'maxLength' => 255],
                        'priority' => ['type' => 'integer'],
                    ],
                    'required' => ['keyword', 'allocation_type'],
                    'additionalProperties' => false,
                ],
                canUse: fn (User $user): bool => $this->canManagementAccounting($user),
                invoke: function (User $user, array $args): array {
                    $rule = $this->managementAccounting->rememberRule($user, $args);

                    return [
                        'rule' => [
                            'id' => $rule->id,
                            'keyword' => $rule->keyword,
                            'allocation_type' => $rule->allocation_type,
                        ],
                    ];
                },
            ),
        ];

        return $this->definitions;
    }

    private function canManagementAccounting(User $user): bool
    {
        return RoleAccess::canAccessManagementAccounting($user);
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
