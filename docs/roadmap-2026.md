# Дорожная карта CRM 2026 (v0.2)

Живой документ для согласования приоритетов. Связан с [`ai-platform-architecture.md`](./ai-platform-architecture.md) (уровни AI, DeepSeek, локальная модель).

**Последнее обновление:** 2026-06-12

---

## Приоритеты (сводка)

| # | Направление | Приоритет | Оценка |
|---|-------------|-----------|--------|
| 1 | MCP для агентов (Cursor + DeepSeek API) | 🔴 P0 | 3–4 нед. |
| 2 | Грид «Диспозиция» (+ плановые задачи 2×/день) | 🔴 P0+ | 3–4 нед. |
| 3 | Timeline на заказе | 🟡 P2 | 1–2 нед. |
| 4 | Workflow end-to-end (лид → налоговая) | 🟢 P3 | spike + TBD |
| 5 | Saved views + «Избранное» (только views) | 🟢 P4 | 2–3 нед. |
| — | Глобальный поиск | 🟡 P2* | см. § «Поиск vs MCP» |
| — | Карта без GPS | ⏸ | не планируется |

\* Порядок относительно Timeline — на усмотрение после старта MCP-1; см. ниже.

---

## Поиск vs MCP — можно ли без MCP и менять местами?

**Да, глобальный поиск работает без MCP.**

| | Глобальный поиск | MCP |
|---|------------------|-----|
| **Что это** | UI + Laravel endpoint: «ввёл текст → список сущностей» | Протокол для **AI-клиентов** (Cursor, будущий агент в command bar) |
| **Нужен LLM?** | Нет | Для Cursor/DeepSeek — да (клиент); сервер MCP — просто typed tools |
| **Кто пользуется** | Менеджер в браузере | Разработчик в Cursor, позже — агент в CRM |
| **Доступ к БД** | Напрямую через Laravel | Тоже через Laravel (tools), не напрямую |

**Общий фундамент (рекомендуется):** один `SearchService` / `EntityLookupService` в PHP. Его используют и Cmd+K, и MCP-tool `search_records`, и DeepSeek function calling.

**Про «поменять местами»:**

- **MCP (инфраструктура) остаётся P0** — без него Cursor не сможет безопасно работать с **продом** (см. § Cursor).
- **Простой поиск (без LLM)** можно поставить **раньше «умного агента»** и даже параллельно MCP-2 — это 3–5 дней, не блокирует MCP.
- **Умный агент в command bar** (то, что вы изначально называли «глобальным поиском») логично **после MCP-1** — он переиспользует те же tools.

**Предлагаемый порядок:**

1. MCP read-only (P0)
2. Диспозиция (P0+)
3. ~~Простой Cmd+K-поиск~~ → **Ctrl/⌘+K = command bar агента** (отдельный SearchService не планируется)
4. Timeline заказа
5. MCP write + DeepSeek в command bar (= «агент») — уже в нижней панели
6. Workflow spike
7. Saved views + Избранное (последними из крупных фич)

---

## Как Cursor работает с продом (без прямого доступа к БД)

Cursor **никогда не подключается к MySQL напрямую**. Схема:

```
[Cursor на вашем ПК]
        │  HTTPS + Bearer token (Sanctum)
        ▼
[https://crm.ваш-домен/mcp/crm]  ← Laravel MCP Web Server (routes/ai.php)
        │  auth:sanctum + visibility_areas
        ▼
[MCP Tools: SearchOrders, GetOrder, …]
        │
        ▼
[MySQL на проде]
```

**На проде поднимаем:**

- MCP Web Server: `Mcp::web('/mcp/crm', CrmServer::class)->middleware(['auth:sanctum', 'throttle:mcp'])`
- Personal Access Token (Sanctum) для каждого пользователя / отдельный service token для разработки
- Каждый tool проверяет права так же, как контроллер CRM

**В Cursor** (`~/.cursor/mcp.json` или настройки проекта):

```json
{
  "mcpServers": {
    "v5-crm-prod": {
      "url": "https://crm.example.com/mcp/crm",
      "headers": {
        "Authorization": "Bearer <sanctum-token>"
      }
    }
  }
}
```

**Локальная разработка:** тот же Cursor может указывать на `http://v5.local/mcp/crm` с локальным токеном — отдельная конфигурация.

**Важно:** токен = права конкретного пользователя CRM. Админский токен не раздавать всем.

---

## DeepSeek — только API-ключ

**MCP-bridge для desktop не нужен.**

Имелся в виду сценарий вроде Claude Desktop: отдельное приложение на ПК с встроенным MCP-клиентом. У DeepSeek вы используете **HTTP API + function calling** — Laravel сам вызывает DeepSeek и отдаёт ему описание тех же PHP-tools (реестр `AgentToolRegistry`).

| Клиент | Транспорт | Где живет логика |
|--------|-----------|------------------|
| Cursor | MCP over HTTPS | Laravel MCP Server |
| DeepSeek | REST API + tools | Laravel (тот же registry) |
| Command bar (агент) | Inertia → Laravel | Laravel (тот же registry) |
| Локальная LLM (позже) | OpenAI-compatible URL | Laravel (тот же registry) |

---

## Фаза 1 — MCP 🔴 P0

### 1.1 Scaffold

- [x] `routes/ai.php`, `App\Mcp\Servers\CrmServer`, web `/mcp/crm` + local `crm`
- [x] `php artisan mcp:issue-token` (Sanctum Bearer)
- [x] Таблица `ai_tool_audit_logs`, rate limit `throttle:mcp`
- [x] `ai_interaction_events` + `get_ai_usage_insights` (аналитика command bar / tools / intake)
- [x] `MCP_DEV_USER_ID` для локального `php artisan mcp:start crm`

### 1.2 Read-only tools (MVP)

- [x] `search_orders`, `get_order`, `get_user_context`
- [x] `search_contractors`, `get_contractor`
- [x] `list_order_documents`
- [x] `search_tasks`, `get_task`
- [x] `search_sales_book_articles`, `upsert_sales_book_article` (Книга продаж)
- [x] Feature tests `tests/Feature/Mcp/TaskMcpServiceTest.php`, `DispositionMcpServiceTest.php`

### 1.3 Write tools (с подтверждением)

- [x] `CreateTaskTool`, `UpsertDispositionEntryTool`
- [x] `AddOrderNoteTool`, `UpdateOrderFieldTool` (whitelist полей inline-грида)
- [x] Dry-run / confirm token для `apply_order_wizard_draft` (остальные write — по мере необходимости)

### 1.4 DeepSeek + command bar

- [x] `AgentToolRegistry` — единый реестр для command bar и DeepSeek tools
- [x] `AiRequestGate` — `local_only` / `external_large` для command bar
- [x] `handleAiSubmit` → `POST /agent/command-bar/chat`, панель `CrmAgentPanel`
- [ ] *Не дублировать* отдельный «глобальный поиск-агент» — он здесь же

### 1.5 Cursor prod checklist

- [x] MCP endpoint на prod за HTTPS
- [x] Документ «Как выпустить MCP-токен» — `docs/mcp-issue-token.md`
- [x] Проверка: Cursor → заказ с прода; Книга продаж — upsert/artisan

### 1.6 Заполнение заказа из заявки заказчика 🔴 P1

**Цель:** загрузить PDF/скан клиентской заявки → получить черновик мастера заказа → менеджер проверяет и сохраняет.

**Не делаем сразу:** полный `update_order` одним tool-call (слишком рискованно). Сначала **extract → preview → apply**.

#### 1.6.1 Извлечение текста

- [x] `DocumentTextExtractor`: PDF (текстовый слой), DOCX; предупреждение для сканов/фото
- [x] Лимиты: `config/documents.php`, sanitizer перед внешним LLM
- [x] Поддержка: PDF, JPG/PNG, DOCX (как в реестре документов)
- [x] Локальный OCR sidecar: [`deploy/ocr/`](../deploy/ocr/), [`docs/order-intake-ocr-service.md`](./order-intake-ocr-service.md), `OcrServiceClient` → подключён в `DocumentTextExtractor` (PDF без слоя, JPG/PNG)

#### 1.6.2 Структурирование (LLM)

- [x] `OrderIntakeSchema` — JSON под subset мастера
- [x] `OrderDocumentIntakeService::extractFromUpload()` — файл → текст → structured JSON + `confidence` + `warnings`
- [x] Сопоставление контрагента: `OrderIntakeContractorResolver` по ИНН/названию

#### 1.6.3 UI мастера заказа

- [x] Блок «Заполнить из заявки» на новом заказе
- [x] Preview распознанных полей + предупреждения
- [x] «Применить к форме» — merge в `form`, без автосохранения

#### 1.6.4 AI / MCP tools

- [x] `get_order_intake_draft` / `list_order_intake_drafts` / `create_order_intake_draft_from_text` — MCP и command bar
- [x] `search_mail_threads` / `get_mail_thread` / `get_mail_sync_status` — MCP (IMAP sync, область «Почта»)
- [x] `send_mail` / `reply_mail_thread` — отправка из CRM (SMTP, From = email сотрудника)
- [x] `extract_order_draft_from_document` — base64 в MCP; в CRM — command bar / POST intake
- [x] `apply_order_wizard_draft` — запись через `OrderWizardService` после confirm token
- [x] Command bar: загрузка файла в чате (`CrmCommandBar` → `CommandBarAttachmentService`, PDF/DOCX → intake)
- [x] Управленческий учёт: MCP tools (`list_management_statement_*`, `allocate_*`, аналитика, правила `management_reconcile_rules`)

#### 1.6.5 Аудит

- [x] Таблица `order_intake_drafts`: исходный файл, text hash, JSON draft, user_id, model
- [x] Запись в ленту заказа при apply (`order_intake_applied`)

**Критерий готовности MVP:** менеджер загружает типовую PDF-заявку → за 1–2 мин получает заполненные клиент, маршрут и груз в новом заказе, правит и сохраняет.

---

## Фаза 2 — Грид «Диспозиция» 🔴 P0+

**Календарь = этот грид.** Отдельный calendar-view и карта без GPS **не планируются**.

### 2.1 Модель данных

- [x] Таблица `disposition_entries`, модель, enum слотов `morning` / `evening`

Таблица `disposition_entries` (или аналог):

| Поле | Описание |
|------|----------|
| `order_id` | Заказ «в пути» |
| `date` | Календарный день (локаль TZ компании) |
| `slot` | `morning` \| `evening` |
| `location` | Местонахождение (город/точка) |
| `comment` | Опционально; дублируется в ленту заказа |
| `recorded_at` | Фактическое время внесения менеджером |
| `recorded_by` | user_id |

Уникальность: `(order_id, date, slot)`.

На заказе: `planned_arrival_date` (или существующее поле ETA) — **последний столбец строки**.

### 2.2 UI грида

- [x] v0: страница `/disposition`, меню **Планирование → Диспозиция**, AG Grid, inline-сохранение
- [x] Диапазон дат: min(погрузка незакрытых рейсов) … max(выгрузка); горизонтальный скролл + нижняя полоса
- [x] Колонки: заказ, клиент, маршрут, **парк**, фильтры в шапке, глобальный поиск, плотность строк
- [x] Якорь скролла: левый край = колонка «сегодня» (просрочка — подсветка всей строки)

**Оси:**

- **Строки:** заказы в статусе «в пути» (свой парк + чужие машины — фильтр/колонка типа перевозки).
- **Столбцы:** даты; на каждый день **4 подстолбца:**
  1. Утро — местоположение
  2. Утро — комментарий
  3. Вечер — местоположение
  4. Вечер — комментарий
- **Закреплено слева:** идентификатор заказа (+ опционально маршрут кратко).
- **Закреплено справа:** планируемая дата прибытия.
- **Горизонтальный скролл:** влево — до первой даты незакрытых рейсов; вправо — до max(планируемая дата прибытия). **Левый видимый край по умолчанию = сегодня.**

**Поведение:**

- Inline-редактирование ячеек; при сохранении — `recorded_at` + `recorded_by`.
- Комментарий → запись в timeline/notes заказа (связь с фазой 3).
- Быстрый ввод: Tab между ячейками, Enter — сохранить.

### 2.3 Плановые задачи (2×/день)

- [x] Scheduler 10:00 и 16:00 MSK (`disposition:remind-unfilled-slots`, `config/disposition.php`)
- [x] Задача менеджерам с незаполненным утренним/вечерним слотом за сегодня (местоположение)
- [x] Автозакрытие задачи при сохранении ячейки с местоположением
- [x] Уведомление в кабинет при назначении задачи (`CabinetNotifier`)

### 2.4 KPI (v2, после стабилизации)

- [x] % заказов с заполнением обоих слотов за день (`DispositionKpiService`)
- [x] Средняя задержка обновления (recorded_at vs дедлайн слота 10:00 / 16:00 MSK)
- [x] Виджет на дашборде для admin/supervisor + панель KPI на `/disposition`

### 2.5 Критерии готовности

- [x] Критерий «в пути»: фактическая погрузка без фактической выгрузки (`DispositionInTransitResolver`)
- [x] UX диспозиции: single-click, Tab/Enter, подсветка пустых слотов за сегодня, Ctrl/⌘+K → command bar
- [x] Руководитель видит все «в пути» на одном экране
- [x] Задачи 2×/день работают

---

## Фаза 3 — Timeline на заказе 🟡 P2

- [x] Комментарии диспозиции → `activity_events` на заказе
- [x] Вкладка **Лента** в мастере заказа + API `orders.activity-timeline`
- [x] `OrderActivityTimelineService` — ledger + статусы, задачи, документы (v1)
- [x] MCP tool: `get_order_timeline`
- [x] Блок почты по заказу (цепочки + ссылка «Написать») — вкладка «Лента» мастера заказа
- [ ] Письма как события в timeline заказа — **отложено** до агента с чтением/анализом почты

---

## Фаза 4 — Глобальный поиск ⏸ отложено

**Решение (2026-06):** отдельный «поиск без ИИ» не делаем. Поиск сущностей — через **агента** в command bar (`search_orders`, `search_contractors`, … в `AgentToolRegistry`). Горячая клавиша **Ctrl+K / ⌘+K** фокусирует поле ассистента в нижней панели.

- [x] Ctrl/⌘+K → фокус command bar (агент)
- [ ] *Опционально позже:* быстрый offline-поиск по текущему гриду (уже есть quick filter в AG Grid)
- [ ] *Не планируется:* отдельный `SearchService` + режим «Поиск без ИИ» в command bar

---

## Фаза 5 — Workflow end-to-end 🟢 P3

**Идея:** сквозной pipeline «Лид → Заказ → Документы → Бухгалтерия → Сдан в отчёт».

**Spike-документ:** [`workflow-end-to-end-spike.md`](./workflow-end-to-end-spike.md)

### Spike (1 нед., без prod-кода)

- [x] Карта статусов: lead / order / document / finance
- [x] 2–3 реальных сценария до «налоговой» / готовности к учёту
- [x] Решение: **read-model `EndToEndPipelineSnapshot`**, не новая `ProcessInstance`; `business_processes` — только лиды
- [x] Wireframe «сквозного канбана» для руководителя (7 колонок, blockers)

### Реализация (после согласования §6 spike)

- [x] `EndToEndPipelineSnapshot` + API board (MVP: `PipelineController`, `PipelineBoardService`)
- [x] `Pipeline/Index.vue` — канбан для руководителя (заказы + лиды по БП, drag этапов)
- [x] KPI полоска (лид→заказ, лид→closed, % просрочки оплат; blockers по overdue на карточке)
- [x] Опционально: `accounting_handoff_at` — ручная галочка «принято бухгалтерией» (без 1С)

*Не блокирует фазы 1–3.*

---

## Фаза 6 — Saved views + «Избранное» 🟢 P4 (последняя из крупных)

**Только saved views** — без «последних заказов» и произвольных ссылок.

### 6.1 Модель

```
grid_views:
  grid_key, name, owner_user_id
  visibility: private | role | users | workspace
  shared_with: json
  filter_state, sort_state, column_state: json
  is_pinned_sidebar, sort_order
```

### 6.2 UI

- [x] Dropdown «Представления» над гридом (сохранить / сохранить как / сбросить)
- [x] Блок «Избранное» в сайдбаре — только закреплённые views
- [ ] Шаринг в UI: роли «Администратор», «Руководитель» → пользователи / группы / workspace (API готов)

### 6.3 Порядок гридов

1. Заказы
2. Диспозиция
3. Документы
4. Лиды, контрагенты, финансы

*Существующие role table presets не ломаем — views = слой поверх.*

---

## Модули — «Сколько влезет» + печатные формы 🟡 P3 (идея)

**Цель:** из карточки заказа подтянуть данные по грузу (позиции, габариты, вес, упаковка) в модуль **«Сколько влезет?»**, рассчитать схему погрузки и сохранить результат для вставки в печатную форму (DOCX/PDF).

### Задачи (черновик)

- [ ] Кнопка / действие в заказе: «Открыть в „Сколько влезет“» с передачей `order_id` и снимка груза (`cargo_items`, ТС, ограничения кузова).
- [ ] Обратная связь: сохранённая схема погрузки привязана к заказу (JSON + опционально превью).
- [ ] Каталог плейсхолдеров печати: таблица/схема раскладки, итоги по осям, комментарий логиста.
- [ ] Шаблон DOCX: макросы `${loading_scheme_*}` / legacy-алиасы при необходимости.

*Связано:* `resources/js/Pages/Modules/HowMuchFits.vue`, мастер заказа (`cargo_items`, `OrderPrintFormDraftService`).

---

## Отложено / не делаем

| Тема | Статус |
|------|--------|
| Карта местоположений без GPS | ⏸ |
| No-code custom objects | ⏸ |
| Record page builder | ⏸ |
| DeepSeek desktop MCP-bridge | ❌ не нужен (только API) |

---

## Ближайшие 2 недели

**Неделя 1**

- [x] MCP scaffold + read-only tools (заказы, контрагенты, задачи, документы заказа)
- [x] Cursor → prod token, Книга продаж (artisan / upsert)
- [x] Миграция `disposition_entries` + грид v0 (read + inline edit)
- [x] Задачи 2×/день + комментарии диспозиции в ленте заказа
- [x] KPI диспозиции (виджет руководителя + панель на странице грида)
- [x] Скролл к колонке «сегодня»; инструкция `docs/disposition-user-guide.md`

**Неделя 2**

- [x] MCP write: `create_task`, `upsert_disposition_entry`
- DeepSeek registry (без UI агента — smoke test)
- Грид диспозиции v0 (read + inline edit, без задач 2×/день)

---

## История версий

| Версия | Дата | Изменения |
|--------|------|-----------|
| v0.1 | 2026-05-28 | Первый черновик |
| v0.2 | 2026-05-31 | Поиск vs MCP; диспозиция вместо календаря; saved views → P4; Cursor/DeepSeek схемы |
| v0.2.1 | 2026-05-28 | MCP 1.2: контрагенты, задачи, документы заказа, Книга продаж |
| v0.2.2 | 2026-05-31 | Диспозиция v0: грид, `disposition_entries`, меню в Планировании |
| v0.2.3 | 2026-05-31 | Диспозиция: напоминания 2×/день, лента заказа, автозакрытие задач |
| v0.2.4 | 2026-05-31 | Диспозиция UI: парк, поиск, плотность; `OrderActivityTimelineService`, MCP `get_order_timeline` |
| v0.2.5 | 2026-05-31 | KPI диспозиции: `DispositionKpiService`, дашборд руководителя, панель на гриде |
| v0.2.6 | 2026-05-31 | Скролл диспозиции: `DispositionUnclosedTrip`, якорь по незакрытым рейсам |
| v0.2.7 | 2026-06-08 | Intake: command bar — вложения в чате ✅; MCP `extract_order_draft_from_document` — открыт |
| v0.2.8 | 2026-06-08 | Единая модель условий оплаты: транши с якорями и событиями; `installment_sequence`; грид оплат — даты дд.мм.гггг; см. `payment-schedule-architecture.md` |
| v0.2.9 | 2026-06-03 | Фаза 5 spike: `workflow-end-to-end-spike.md` — карта статусов, сценарии, read-model вместо ProcessInstance, wireframe pipeline |
| v0.3.0 | 2026-06-11 | Управленческий учёт в «Финансы»: импорт XLSX Сбера, разнесение, ФОТ 5/20, флаг `can_management_accounting`; см. `management-accounting-architecture.md` |
| v0.3.1 | 2026-06-12 | Идея: заказ → модуль «Сколько влезет?» → схема погрузки в печатную форму |

---

## Открытые вопросы

- [ ] Точное время слотов «утро/вечер» (до 12:00 / после 12:00 или иное)
- [x] «В пути» для диспозиции — между фактической погрузкой и выгрузкой (не только enum статуса)
- [x] Prod-домен для MCP: `https://crm.avtoaliyans.ru/mcp/crm` — в `.env.example` и `docs/mcp-issue-token.md`
