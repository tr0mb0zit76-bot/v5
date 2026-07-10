# Code audit CRM v5 — июль 2026

> **Источник:** read-only аудит кодовой базы (3 subagents: architecture, quality, security), синтез в чате 2026-07-09/10.  
> **Статус исправлений:** `master` @ `f6a1ec2` (2026-07-10).  
> **Handoff:** [[Cursor-handoff-latest]] · **Канон IDOR:** `app/Support/OrderViewAuthorization.php`

---

## Сильные стороны (не ломать)

| Область | Почему |
| --- | --- |
| Сервисный слой | Бизнес-логика вынесена из контроллеров (график оплат, печать, управленка, Load Board) |
| Тесты | Payment schedules, print forms, `RoleAccess`, loading planner — хорошее покрытие паттернов |
| Load Board | Presenter/Advisor pattern — образец для декомпозиции Wizard |
| RBAC | `RoleAccess` + `visibility_areas` / `visibility_scopes` — единая модель, но **не везде доведена до backend** |

---

## Roadmap исправлений (Phase 0 → 3)

### Phase 0 — безопасность и целостность данных (критично)

| # | Проблема | Статус | Коммиты / файлы |
| --- | --- | --- | --- |
| 0.1 | **IDOR** `GET orders/{order}/edit` — только `manager_id` | ✅ | `911fb7b` · `OrderViewAuthorization`, `OrderWizardController::edit()` |
| 0.2 | **IDOR** transport-summary, documents modal, activity timeline scope, payment schedule scope | ✅ | `4b7e9b7` · контроллеры + `OrderDocumentAccessAuthorization` |
| 0.3 | **IDOR** MCP `findAccessibleOrder`, mobile feed/chips, document registry list | ✅ | `4b7e9b7`, `ae7e9d9` · `McpAccessGate`, `MobileShellFeedService`, `DocumentRegistryController` |
| 0.4 | **График оплат** `syncPaymentSchedules` без транзакции | ✅ | `911fb7b` · `OrderCompensationService` + `DB::transaction()` |
| 0.5 | **Play → CRM** TOCTOU (два клика создают дубль) | ✅ | `911fb7b` · `SalesScriptCrmActionService` + `lockForUpdate()` |
| 0.6 | Partial payment сбрасывал `overdue` → `pending` | ✅ | `4b7e9b7` · `PaymentScheduleSettlementSyncService` |
| 0.7 | MCP-токены без срока | ✅ | `mcp:issue-token --days=90`; глобально `sanctum.expiration` = 90 суток (`SANCTUM_EXPIRATION`, 0 = off) |
| 0.8 | XSS agent markdown (`v-html` без sanitize) | ✅ | `4b7e9b7` · `dompurify` в `renderAgentMarkdown.js` |
| 0.9 | System transport templates редактируемы всеми | ✅ | `4b7e9b7` · `LoadingPlannerController::ensureCanMutateTransportTemplate` |
| 0.10 | **Остаточные IDOR / scope** — см. grep `manager_id` + `scope !== 'all'` | ⏳ | `FinanceOverviewService`, `ContractorReconciliationService`, `PeriodCalculator`, `DispositionInProgressOrderScope` (частично ✅), задачи/контрагенты |

### Phase 1 — visibility и доменная согласованность

| # | Проблема | Статус | Действие |
| --- | --- | --- | --- |
| 1.1 | **`department` scope в UI, не в backend** | 🟡 частично | ✅ заказы: `applyOrdersVisibilityScope`; ✅ лиды: `PipelineBoardService`; ⏳ `PaymentScheduleAutomaticStatus::refreshForOrdersScope`, finance journal scope, leads в `LeadAttentionQueueService` |
| 1.2 | **`order_owner_id` / `dispatcher_id`** не учитывались в IDOR | ✅ | `OrderViewAuthorization::userOwnsOrderRecord` |
| 1.3 | Неиспользуемые permissions `create_orders` / `edit_orders` | ⏳ | Проверить `RoleAccess` / policies; либо wire-up, либо удалить из UI ролей |
| 1.4 | Legacy статус заказа vs `hasFactOfLoadingOnRoute` | ⏳ | `OrderStatusService` — сверить чеклист закрытия и автостatus |
| 1.5 | Activity timeline заказа — **только admin** на `showForOrder` | ❓ | Решить: фича для админов или открыть `OrderViewAuthorization` |
| 1.6 | MCP `abilities: *` по умолчанию | ✅ | `mcp:read` по умолчанию, `--write` / `mcp:write`; gate в `McpAccessGate` + `McpTokenAbilities` |
| 1.7 | Smart-link «Документы N» под номером заказа | ✅ убрано | `341c7dc` · bar удалён из Wizard, `smart_links` пустой |

### Phase 2 — поддерживаемость (крупные рефакторинги)

| # | Проблема | Статус | Действие |
| --- | --- | --- | --- |
| 2.1 | **Order Wizard ~12k строк** (`Wizard.vue` + fat controller) | 🟡 | Slices 1–5: controller **~386**; Vue **~5770** (было ~7180); tabs: Main, Route, Cargo, Finance, Mail, Timeline, Norms |
| 2.2 | `OrderWizardController` дублирует serialize/authorize | ✅ | `OrderWizardOrderPresenter`, `PagePresenter`, `OrderAuthorization`, `DocumentSerializer` |
| 2.3 | Другие `v-html` (Mermaid, PublicSiteShell) | ⏳ | Аудит; DOMPurify или escape |

### Phase 3 — UX / продукт (после Phase 0–1)

| # | Задача | Статус |
| --- | --- | --- |
| 3.1 | Уплотнение мастера заказа (Основное, Маршрут, Груз, Финансы) | ✅ `54fab5f`…`341c7dc` |
| 3.2 | Уплотнить вкладки Нормативы, Документы, Переписка | ⏳ |
| 3.3 | Дашборд «Мои / Всего» + клик «просрочено» → фильтр | ⏳ (обсуждалось отдельно) |

---

## Уже сделано — коммиты (хронология)

```
eed02a1  loading-planner: admin/supervisor see all projects
4504646  loading-planner: redirect after delete
911fb7b  audit phase 0: OrderViewAuthorization, transaction, Play lock
4b7e9b7  audit phase 1: IDOR spread, overdue, MCP TTL, XSS, transport templates
ae7e9d9  department scope orders + leads pipeline
54fab5f  wizard UI compaction (route stages, hints)
341c7dc  remove smart-link bar, tighter finance
c54526b  chore: unused import
f6a1ec2  docs: handoff
```

**Прод:** всё выше выкатано (`git pull` + `npm run build` + `optimize:clear`). **Миграций нет.**

---

## Следующие шаги (порядок для «большого ПК»)

1. **`PaymentScheduleAutomaticStatus::refreshForOrdersScope`** — заменить `where('manager_id', $userId)` на `OrderViewAuthorization::applyUserIdsOwnsOrderScope` или department user ids.
2. **Finance scope** — `FinanceOverviewService`, `ContractorReconciliationService`, `PaymentScheduleAutomaticStatus` (grep `manager_id` + orders scope).
3. **Leads department** — `LeadAttentionQueueService`, `CommercialNudgeProcessor` scope (pipeline ✅).
4. **Тесты** — прогнать на машине с MySQL:  
   `php artisan test --compact tests/Unit/OrderViewAuthorizationTest.php tests/Feature/Orders/OrderTransportSummaryTest.php tests/Unit/Services/Mcp/McpAccessGateOrderScopeTest.php tests/Unit/PaymentScheduleSettlementSyncServiceTest.php`
5. **Wizard UI** — уплотнить Нормативы / Документы / Переписка (по аналогии с Финансами).
6. **Phase 2** — только после закрытия Phase 0.10 и 1.1: план декомпозиции Wizard (отдельная ветка/PR).

---

## Команды на втором ПК (**ЗАБРАТЬ**)

```powershell
cd C:\OSPanel\home\v5.local   # или свой путь
git pull
pwsh -File scripts/sync-docs-to-yandex.ps1   # если vault не в дефолтном пути — -ExchangeRoot
```

Читать:

1. `docs/sync/Cursor-handoff-latest.md`
2. **Этот файл** (`docs/sync/v5-local-Components-Code-Audit-2026-07.md`)
3. `AGENTS.md` → домен приложения

PHPUnit локально: нужен `mysql` в PATH (OSPanel) или `.env.testing` + `u_tromb_test`.

---

## Grep-шпаргалка (остатки scope)

```text
manager_id === $user->id
where('manager_id', $user->id)
scope !== 'all'
```

Ключевые файлы для ревью:

- `app/Support/PaymentScheduleAutomaticStatus.php`
- `app/Services/Finance/FinanceOverviewService.php`
- `app/Services/Finance/ContractorReconciliationService.php`
- `app/Services/Commercial/LeadAttentionQueueService.php`
- `app/Http/Controllers/ActivityTimelineController.php` (admin gate)

---

## Не в git (локально на ноутбуке, не тянуть)

- `docs/roadmap-2026.md`, `docs/saas-roadmap.md` — черновики
- `scripts/repair-order-*`, `scripts/fix-order-5-*` — одноразовые prod probes

---

*Обновлено: 2026-07-10 · при закрытии пунктов audit — править таблицы статусов и HEAD в handoff.*
