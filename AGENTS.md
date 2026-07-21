<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines


The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.


## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

### Контекст проекта (оба ПК — читать в начале сессии)

Перед правками кода **не полагайся только на память прошлых чатов**. Сначала:

1. При необходимости `git pull`; на втором ПК после pull — `pwsh -File scripts/sync-docs-to-yandex.ps1`.
2. Прочитай `docs/sync/Cursor-handoff-latest.md`, затем `docs/sync/cursor-agent-startup.md`, затем раздел «Домен приложения» ниже.
3. По теме задачи — карточку в `docs/sync/v5-local-Components-*.md`.

Правило Cursor: `.cursor/rules/project-context-handoff.mdc` (`alwaysApply`). Vault на Я.Диске (`Exchange/CRM/`) синхронизируется **из git**, не заменяет `git pull`.

**SSH на прод (агенты):** только `.\scripts\prod-plink.ps1` → IP **`91.229.11.16`**, PPK **`C:\,ssh\private_key.ppk`**. **Не** `109.61.108.18`, **не** ручной `plink`, **не** путь `.../crm.avtoaliyans.ru/` на диске. См. `.cursor/rules/prod-ssh.mdc`, `docs/sync/prod-ssh.md`.

- php - 8.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v2
- laravel/ai (AI) - v0
- laravel/framework (LARAVEL) - v13
- laravel/mcp (MCP) - v0
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- tightenco/ziggy (ZIGGY) - v2
- laravel/boost (BOOST) - v2
- laravel/breeze (BREEZE) - v2
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/vue3 (INERTIA_VUE) - v2
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3



## Skills Activation


This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `ai-sdk-development` — TRIGGER when working with ai-sdk which is Laravel official first-party AI SDK. Activate when building, editing AI agents, chatbots, text generation, image generation, audio/TTS, transcription/STT, embeddings, RAG, vector stores, reranking, structured output, streaming, conversation memory, tools, queueing, broadcasting, and provider failover across OpenAI, Anthropic, Gemini, Azure, Groq, xAI, DeepSeek, Mistral, Ollama, ElevenLabs, Cohere, Jina, and VoyageAI. Invoke when the user references ai-sdk, the `Laravel\Ai\` namespace, or this project's AI features — not for Prism PHP or other AI packages used directly.
- `laravel-best-practices` — Apply this skill whenever writing, reviewing, or refactoring Laravel PHP code. This includes creating or modifying controllers, models, migrations, form requests, policies, jobs, scheduled commands, service classes, and Eloquent queries. Triggers for N+1 and query performance issues, caching strategies, authorization and security patterns, validation, error handling, queue and job configuration, route definitions, and architectural decisions. Also use for Laravel code reviews and refactoring existing Laravel code to follow best practices. Covers any task involving Laravel backend PHP code patterns.
- `mcp-development` — Use this skill for Laravel MCP development only. Trigger when creating or editing MCP tools, resources, prompts, or servers in Laravel projects. Covers: artisan make:mcp-* generators, mcp:inspector, routes/ai.php, Tool/Resource/Prompt classes, schema validation, shouldRegister(), OAuth setup, URI templates, read-only attributes, and MCP debugging. Do not use for non-Laravel MCP projects or generic AI features without MCP.
- `inertia-vue-development` — Develops Inertia.js v2 Vue client-side applications. Activates when creating Vue pages, forms, or navigation; using <Link>, <Form>, useForm, or router; working with deferred props, prefetching, or polling; or when user mentions Vue with Inertia, Vue pages, Vue forms, or Vue navigation.
- `tailwindcss-development` — Always invoke when the user's message includes 'tailwind' in any form. Also invoke for: building responsive grid layouts (multi-column card grids, product grids), flex/grid page structures (dashboards with sidebars, fixed topbars, mobile-toggle navs), styling UI components (cards, tables, navbars, pricing sections, forms, inputs, badges), adding dark mode variants, fixing spacing or typography, and Tailwind v3/v4 work. The core use case: writing or fixing Tailwind utility classes in HTML templates (Blade, JSX, Vue). Skip for backend PHP logic, database queries, API routes, JavaScript with no HTML/CSS component, CSS file audits, build tool configuration, and vanilla CSS.


## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.


## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.


## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Домен приложения (актуальная карта кода)

Кратко, что важно знать при правках (Laravel + Inertia Vue 3 + Tailwind).

### Роли и видимость модулей

- Правила областей: `app/Support/RoleAccess.php` (`effectiveVisibilityAreasFromRolePayload`, `hasVisibilityArea`, легаси `scripts` / подмодули помощника продавца).
- Страница ролей: `resources/js/Pages/Roles/Index.vue` — колонки в `roleColumns`, сохранение через `router.patch`, после успеха `replaceRoleColumnsFromInertiaPage(page)`; `visibility_areas` перед отправкой фильтруются по актуальному списку из `visibilityAreaOptions` (`sanitizeVisibilityAreas`), чтобы не ломать валидацию легаси-ключами.
- Общие данные авторизации: `app/Http/Middleware/HandleInertiaRequests.php` — `auth` и часть связанных пропсов обёрнуты в `Inertia::always(...)`, чтобы при частичных reload (`only`) не залипали старые `visibility_areas`.
- Меню CRM: `resources/js/Layouts/CrmLayout.vue` — `visibleAreas` из `auth.user.role.visibility_areas`; у пунктов с проверкой должно быть поле `visibilityArea` (в т.ч. дашборд).
- Middleware маршрутов: `EnsureVisibilityAreaAccess`, `EnsureVisibilityAnyAreaAccess` — чтение роли через `Role::query()->find($user->role_id)`, без зависимости от частично загруженного relation на `User`.
- CRUD ролей: `app/Http/Controllers/RoleManagementController.php` — в БД уходит явный набор полей (не «сырой» spread всего `validated()` для критичных атрибутов); колонка `default_mobile_nav_keys` на `roles` (если есть в схеме) — дефолт нижней панели для пользователей роли.
- Валидация: `app/Http/Requests/StoreRoleRequest.php`, `UpdateRoleRequest.php` — у `visibility_areas` минимум одна область; сообщения на русском.

### Печатные формы (DOCX)

- Заказ: `app/Services/OrderPrintFormDraftService.php` — снимок данных, `TemplateProcessor`, подстановка плейсхолдеров `${…}` и `{{…}}`.
- Лид: `app/Services/LeadPrintFormDraftService.php` — аналогично.
- Карта плейсхолдеров → путь в снимке: `app/Support/PrintFormPlaceholderPathResolver.php` (legacy-имена вроде `stoimost`, `dolzhn_podpisant_rod`).
- Варианты имён макросов для `setValue`: `app/Support/PrintFormPlaceholderMacroVariants.php` — **только точное имя**, без вариантов с пробелами (чтобы не портить вёрстку вокруг плейсхолдера).
- Каталог переменных для UI шаблонов: `app/Services/PrintFormVariableCatalog.php`.
- Суммы с валютой в одном поле: `order.customer_rate_with_currency`, `order.carrier_rate_with_currency` (легаси `stoimost*` мапятся на них).
- Workflow печати заказа: `OrderPrintDocumentWorkflowService` — черновик → согласование → `materializeSignedPrintArtifacts()` → финальный PDF.
- QR проверки: плейсхолдеры `document_verification_code`, `document_verification_qr`; код `PrintFormVerificationCode` (отдельный на каждый `OrderDocument`); размеры `PrintFormVerificationQrDimensions` / `config/documents.php` → `verification_qr`. Контекст `OrderPrintFormContext` (`documentVerificationCode`, `orderDocumentId`, `forTemplatePreview`). VML: QR не участвует в смещениях подписи/печати (`DocxVmlOverlayStylePatcher`, `countVerificationQrVmlShapes`). Публичная страница **без auth**: `GET /verify/order-documents/{orderDocument}?code=…` — контрагент по `metadata.party` (`PrintVerificationPageScope`). Документация: `docs/print-form-pdf-protection.md`, карточка `docs/sync/v5-local-Components-Print-Forms-Verification.md`.
- DocMDP (опционально, `PDF_CERTIFY_ENABLED`): `PdfDocumentCertificationService`, `config/pdf_signing.php`, `php artisan pdf-signing:generate-certificate`. После Gotenberg: QR-штамп (`PdfVerificationQrStampService`) → certify. Хеши в `order_documents.metadata`: `pdf_certified_sha256`, `pdf_verification_*`.

### Saved views гридов (P4)

- Модель `grid_views`, API `GridViewController` (`/grid-views`, auth), сервис `GridViewService`, каталог `GridViewCatalog` (ключи гридов + URL с `?view=`).
- UI: `resources/js/Components/Grid/GridViewsBar.vue`, клиент `resources/js/support/gridViews.js` (`fetch` с `redirect: 'manual'` — иначе DELETE при 302 на login ломается).
- Избранное в сайдбаре: `HandleInertiaRequests` → `auth.user.pinned_grid_views`, блок в `CrmLayout.vue`.
- Миграция: `2026_06_12_162154_create_grid_views_table.php` — **обязательна** (`php artisan migrate`), иначе грид заказов падает с `Unexpected token '<'`.

### Склонение должности (родительный падеж, без второго поля ввода)

- `app/Support/RussianPositionInflector.php` — эвристики + fallback на исходную строку.
- В снимок контрагента добавлено `*.signer_position_genitive_auto` в сервисах печати заказа/лида; в legacy добавлены алиасы `dolzhn_podpisant_rod`, `podpisant_perevoz_rod`.

### Скрипты продаж (граф, Play, поля)

- Документация: `docs/sales-scripts-editor-guide.md`, план фаз: `docs/scripts-module-implementation-plan.md`.
- Редактор: `SalesScriptEditorController`, `resources/js/Pages/SalesScripts/Editor/Graph.vue`, канвас `ScriptGraphCanvas.vue` — теги узлов (`tags`), шаблоны (`sales_script_node_templates`), поля `{code}` (`sales_script_capture_fields`, `capture_field_codes` на узле).
- Play: `SalesScriptController`, `Play.vue`, `SalesScriptPlaySessionService::saveFieldValues()`, `SalesScriptPlayPresentationService` + `SalesScriptBodyPlaceholderService` (сегменты capture/reference).
- Маршруты редактора: `scripts.editor.*` — `capture-fields.*`, `node-templates.*`, сохранение графа с тегами (`SaveGraphRequest`).
- Область видимости: `sales_assistant_scripts` (легаси `scripts` в `RoleAccess`).

### Управленческий учёт (Финансы)

- Документация: `docs/management-accounting-architecture.md`, план фаз: `docs/management-accounting-implementation-plan.md`.
- Доступ: `users.can_management_accounting` + admin; `RoleAccess::canAccessManagementAccounting()`. Не путать с бюджетированием (`belongs_to_management`).
- Импорт: `ManagementAccountingImportService`, парсер `SberRegistryXlsxParser` (`sber_registry_v1`).
- Матчинг / разнесение: `ManagementAccountingMatchingService` (правила → номер заявки → контрагент+сумма → **входящие только по сумме** → ФОТ → статьи; `suggested_candidates[]` с `amount_due` при неоднозначности), `ManagementAccountingAllocationService` → при операционном типе `PaymentSchedulePaymentLedgerService`; переразнесение — `PaymentScheduleSettlementSyncService`.
- UI разнесения: `Reconcile.vue` — входящие по умолчанию «Операционный», автокандидаты, «к оплате» в списке.
- ФОТ полупериоды (5 / 20): `ManagementPayrollHalfCalendar`, `ManagementPayrollHalfService`.
- UI: `Finance/ManagementAccounting/Index.vue` (variance, ручные операции), `Reconcile.vue` (split); `Budgeting/Index.vue` (freeze плана); меню `finance-management-accounting`.
- Маршруты: `finance.management-accounting.*`, `budgeting.plan-snapshots.store`; статьи: `POST categories`, `POST categories/sync`.
- План vs факт: `BudgetPlanSnapshotService`, `BudgetVarianceService`, `ManagementAccountingAnalyticsService` (`plan_source`, `variance_rows`); см. `docs/management-accounting-budgeting-integration.md`.
- Split: `management_statement_line_splits`, `allocations[]` при разнесении.
- Справочник статей: `ManagementExpenseCategoryCatalog`, `ManagementExpenseCategorySyncService` (системные + `budget_opex_*`).
- Правила разнесения: `management_reconcile_rules`, `ManagementReconcileRuleService` — приоритет в матчинге до эвристик.
- MCP (`/mcp/crm`, домен `finance`): `ManagementAccountingMcpService`, tools `list_management_statement_*`, `suggest_*`, `allocate_*`, `get_management_accounting_analytics`, `*_management_reconcile_rule*`, `list_management_expense_categories`; gate `McpAccessGate::requireManagementAccounting()`.
- Факт вкладки «Учёт»: разнесённые `management_statement_lines` **+** `payment_schedule_payment_events` (без дублей `mgmt:*`); backfill: `payment-schedules:backfill-payment-events`.

### Лиды

- Отказ (`lost` / этап БП): `LeadLinkedTaskService` отменяет открытые задачи; flash `lead_follow_up` в `Leads/Wizard.vue`.
- `TaskController::syncLinkedLeadStatus` не перезаписывает закрытые лиды (`LeadStatus::isClosed`).
- Терминальный этап БП: `LeadBusinessProcessService::progressPayload` → 100%; playbook без `auto_create_task` на terminal.
- **Playbook этапов БП:** `BusinessProcessPlaybook`, `BusinessProcessDefaultPlaybookLibrary`, `BusinessProcessPlaybookSeederService`; поля на `business_process_stages` (`coaching_hint`, `sales_script_id`); сидер `php artisan business-processes:seed-playbooks`; UI — `Settings/BusinessProcesses/Index.vue` (`CrmMarkdownEditor`).
- MCP (`/mcp/crm`): `LeadMcpService` — `search_leads`, `get_lead`, `update_lead_field`, `create_lead_next_step`; gate `requireLeadsArea` / `findAccessibleLead`.

### Дашборд и меню

- Дашборд по подразделению: `users.sees_company_dashboard`, `UserDashboardDepartmentScope`, `DashboardMetricsService` (scope отдела vs вся компания).
- Избранное в сайдбаре: `SidebarMenuCatalog`, `SidebarMenuFavoritesResolver`, `ProfileController::updateSidebarFavorites` (`profile.sidebar-favorites`).

### Считалка (маржа в переговорах)

- Модуль **Модули → Считалка**: `resources/js/Pages/Modules/Counter.vue`, маршруты `modules.counter.index` / `modules.counter.calculate` (`/modules/counter`). Редирект с `/sales-assistant/counter`.
- `SalesMarginCounterService` — ставки заказчик/перевозчик + обязательное правило удержания KPI (`kpi_deduction_rule_id` из активных `kpi_deduction_rules`).
- Сценарии: `cash`, `vat_all`, `vat_zero_cash`; категория KPI `vat_zero_cash` в `KpiPaymentCategoryResolver`.

### Растаможка (калькулятор ввоза)

- Документация: `docs/import-cost-calculator-architecture.md`.
- Модуль **Модули → Растаможка**: `resources/js/Pages/Modules/ImportCostCalculator.vue`, маршруты `modules.import-cost.*` (`/modules/import-cost`). Область `modules_import_cost`.
- Расчёт: `ImportCostCalculatorService` — таможенная стоимость, пошлина, НДС, таможенный сбор, утильсбор (ПП № 1291: `base_fee_rub × coefficient` по возрасту), доставка; суммы до целых ₽.
- Справочники: `ImportCostTnVedCatalog`, `UtilizationFeeCatalog`, `ImportCostReferenceMeta`; БД `import_cost_tn_ved_entries`, `import_cost_pp1291_categories`, `import_cost_reference_syncs`.
- Синхронизация: `php artisan import-cost:sync-references` (`--eec-only`, `--pp1291-only`); cron пн 03:15 (`routes/console.php`); сервисы `EecTnVedSyncService`, `Pp1291ReferenceSyncService`, клиент `EecODataClient`.
- Конфиг: `config/import_cost_calculator.php`, `config/import_cost_pp1291.php`. TKS API не используется.

### Условия оплаты и график (`payment_schedules`)

- Документация: `docs/payment-schedule-architecture.md`.
- Единый формат JSON: `installments[]` (до 10 траншей): `percent`, `amount`, `offset_days`, `offset_unit`, `anchor`, `basis`. Легаси `has_prepayment` / `postpayment_*` → `PaymentScheduleLegacyConverter`.
- Пересборка строк БД: `OrderCompensationService::syncPaymentSchedules()`; сохранение фактических оплат при пересборке — `PaymentScheduleSettlementPreserver` (ключ `installment_sequence` + fallback на `type`).
- Расчёт `planned_date`: событие (`basis`) + сдвиг, либо якорь через `PaymentInstallmentPlanner`; даты погрузки/выгрузки — `OrderRouteMilestoneDateResolver` (факт точки → план → performers → колонка заказа); синхронизация при сохранении мастера и факта на точке; **наличка** — `PaymentScheduleCashBasis` (базисы документов → `unloading`).
- Частичные оплаты: `PaymentScheduleSettlementStatus`, колонка «К оплате» в `CashFlowGrid.vue`; после деплоя правок — `payment-schedules:sync-settlement-amounts`.
- FTTN по сканам — авто (`OrderDocumentRequirementService::paymentPackageAttachedAt`); при **наличке** базис `fttn` → срок от выгрузки (`PaymentScheduleCashBasis`).
- Квиток / OTTN — вручную: `track_received_date_*` (в т.ч. **наличка + ottn** / `fttn_receipt`); стороны раздельно.
- UI: `PaymentTermsWizardBlock.vue`, `orderPaymentScheduleUi.js` (`applyInstallmentScheduleInPlace` — без deep-watch циклов); грид — `CashFlowGrid.vue`, даты **дд.мм.гггг**.
- Миграция: `2026_06_08_155321_add_installment_sequence_to_payment_schedules_table.php`.

### Документы и чек-лист заказа

- Реестр + вкладка «Документы»: `DocumentRegistryController`, `OrderWizardDocumentsTab.vue`, `DocumentsGrid.vue`.
- Дата получения оригиналов: `track_received_date_customer/carrier` — clerk в реестре (`PATCH documents/orders/{id}/track-received`) и в таблице учёта (`OrderSignedDocumentsTable.vue`); одна дата на сторону, строки заявки + закрывающих — `orderTrackingDates.js`.
- Слоты обязательных документов: `OrderDocumentRequirementSlotBuilder`, зеркало на фронте `orderDocumentRequirementSlots.js`.
- Транспортные типы (ТН / ЭТрН / CMR / ТСД) — одна группа: `OrderDocumentTransportTypes`, слот `waybill` с `accepted_types` waybill|etrn|cmr.
- **Наличные (`cash`):** закрывающие слоты (УПД / СФ / акт) **не создаются** — только заявка по контрагенту + общий слот ТСД. Форма оплаты: заказчик — `customer_payment_form`; перевозчик — `contractors_costs` / `leg_costs`; подрядчик — `additional_costs.payment_form`.
- Закрытие сделки: `OrderStatusService` → `checklistForOrder()` — все пункты чек-листа должны быть `completed`.
- Документация: `docs/documents-user-guide.md`, `docs/documents-regulation.md`; карточка `docs/sync/v5-local-Components-Documents-Registry.md`; публикация в Книгу: `php scripts/mcp-prod-upsert-documents.php`.

### HTML-шаблоны КП (GrapesJS)

- Модуль **Модули → Шаблоны КП**: `resources/js/Pages/Modules/ProposalTemplates/*`, редактор `Components/ProposalTemplates/ProposalGrapesEditor.vue` (GrapesJS + `grapesjs-preset-newsletter`, MIT).
- Маршруты: `modules.proposal-templates.*` (`/modules/proposal-templates`); область `modules_proposal_templates`; CRUD — `canAccessSettingsSystem`.
- Плейсхолдеры в HTML: `{lead.number}`, `{counterparty.name}` и т.д. — каталог `ProposalHtmlTemplateVariableCatalog` (из `PrintFormVariableCatalog::leadOptions()`); панель переменных **слева** от холста.
- Рендер / PDF: `LeadProposalHtmlRenderer`, `LeadProposalPdfService` (Gotenberg); на лиде — `LeadWizardCommercialTab.vue`.
- Демо-шаблон Unisender: `ProposalHtmlTemplateParallelImportDemo`, seeder `ProposalHtmlTemplateDemoSeeder` (slug `parallel-import-demo`).
- ТЗ: `docs/tz-step-04-html-proposal-builder.md`; карточка `docs/sync/v5-local-Components-Commercial-Roadmap.md`.

### Собственный парк и рейсы

- Виртуальный перевозчик «Собственный парк» (`OwnFleetCatalog`, `OwnFleetContractorService`) — **не** own company в заказе (`is_own_company=false`, исключён из `Contractor::ownCompanyProfiles()`).
- Рейсы (`fleet_trips`): создаются при сохранении заказа только если `performers[].execution_mode === own_fleet` (`FleetTripService::syncPlannedTripsFromOrder`); смена на внешнего перевозчика рейс **не удаляет**.
- Мастер: верхняя кнопка «Собственный парк» в поиске перевозчика (`Wizard.vue` → `selectOwnFleetPerformer`); дубль из списка контрагентов скрыт с `078b41d`.
- Карточка / runbook: `docs/sync/v5-local-Components-Fleet-Own-Fleet.md`.
- PHPUnit: `.env.testing` → `DB_DATABASE=u_tromb_test` (не рабочая `u_tromb`).

### Прочее

- **SSH на прод:** `docs/sync/prod-ssh.md` — IP `91.229.11.16`, ключ PuTTY `C:\,ssh\private_key.ppk`, скрипт `scripts/prod-plink.ps1` (не путать с GitHub-ключом в `~/.ssh`).
- Мобильная нижняя панель: `app/Support/MobileNavCatalog.php` — кандидаты кнопок с учётом `visibility_areas` (дашборд не навязывается, если области нет); итоговый проп для фронта собирает `MobileNavResolver` (`HandleInertiaRequests` → `auth.user.mobile_nav`). Сохранение выбора пользователя: `ProfileController::updateMobileBottomNav`, маршрут `profile.mobile-bottom-nav` (`routes/web.php`).
- PWA: `public/sw.js` — кэш shell для `/`, навигации на другие пути идут через сеть.
- Синхрон индексов Obsidian ↔ git: `docs/sync/`, `scripts/sync-docs-to-yandex.ps1`; MCP bearer с Я.Диска: `scripts/sync-cursor-mcp-from-yandex.ps1`.


## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.


## Documentation Files

- You must only create documentation files if explicitly requested by the user.


## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost



## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.


## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.


## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.


## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP


- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== tests rules ===

# Test Enforcement


- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/Pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

# Inertia v2

- Use all Inertia features from v1 and v2. Check the documentation before making changes to ensure the correct approach.
- New features: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.

=== laravel/core rules ===

# Do Things the Laravel Way


- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.


### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.


## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.


## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.


## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.


## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== pint/core rules ===

# Laravel Pint Code Formatter


- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit


- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.


## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-vue/core rules ===

# Inertia + Vue

Vue components must have a single root element.
- IMPORTANT: Activate `inertia-vue-development` when working with Inertia Vue client-side patterns.

</laravel-boost-guidelines>
