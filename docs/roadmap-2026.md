# Дорожная карта CRM 2026 (v0.2)

Живой документ для согласования приоритетов. Связан с [`ai-platform-architecture.md`](./ai-platform-architecture.md) (уровни AI, DeepSeek, локальная модель).

**Последнее обновление:** 2026-06-02

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
3. **Простой Cmd+K-поиск** (общий SearchService) — *опционально здесь*
4. Timeline заказа
5. MCP write + DeepSeek в command bar (= «агент»)
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
- [ ] Dry-run / confirm token для опасных операций

### 1.4 DeepSeek + command bar

- [x] `AgentToolRegistry` — единый реестр для command bar и DeepSeek tools
- [x] `AiRequestGate` — `local_only` / `external_large` для command bar
- [x] `handleAiSubmit` → `POST /agent/command-bar/chat`, панель `CrmAgentPanel`
- [ ] *Не дублировать* отдельный «глобальный поиск-агент» — он здесь же

### 1.5 Cursor prod checklist

- [x] MCP endpoint на prod за HTTPS
- [ ] Документ «Как выпустить MCP-токен» (1 страница)
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
- [ ] `extract_order_draft_from_document` — прямая загрузка файла в MCP (позже)
- [ ] `apply_order_wizard_draft` — запись через `OrderWizardService` после confirm token
- [ ] Command bar: загрузка файла в чате

#### 1.6.5 Аудит

- [x] Таблица `order_intake_drafts`: исходный файл, text hash, JSON draft, user_id, model
- [ ] Запись в ленту заказа при apply

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

- [ ] Менеджер заполняет строку заказа < 2 мин
- [ ] Руководитель видит все «в пути» на одном экране
- [ ] Задачи 2×/день работают

---

## Фаза 3 — Timeline на заказе 🟡 P2

- [x] Комментарии диспозиции → `activity_events` на заказе
- [x] Вкладка **Лента** в мастере заказа + API `orders.activity-timeline`
- [x] `OrderActivityTimelineService` — ledger + статусы, задачи, документы (v1)
- [x] MCP tool: `get_order_timeline`
- [ ] Письма в ленте заказа — **отложено** до агента с чтением/анализом почты

---

## Фаза 4 — Простой глобальный поиск (без LLM) 🟡 P2*

*Можно выполнить параллельно фазе 3 или сразу после диспозиции — не ждёт «агента».*

- [ ] `SearchService` — заказы, контрагенты, водители, документы, задачи
- [ ] Cmd+K / поле в command bar (режим «Поиск», не «ИИ»)
- [ ] MCP tool `SearchRecordsTool` → тот же сервис
- [ ] *Умный агент* — фаза 1.4, не отдельный проект

---

## Фаза 5 — Workflow end-to-end 🟢 P3

**Идея:** сквозной pipeline «Лид → Заказ → Документы → Бухгалтерия → Сдан в отчёт».

### Spike (1 нед., без prod-кода)

- [ ] Карта статусов: lead / order / document / finance
- [ ] 2–3 реальных сценария до «налоговой»
- [ ] Решение: расширить `business_processes` **или** новая `ProcessInstance`
- [ ] Wireframe «сквозного канбана» для руководителя

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

- [ ] Dropdown «Быстрые фильтры» / chips над гридом
- [ ] Сохранить / сохранить как / сбросить
- [ ] Блок «Избранное» в сайдбаре — только закреплённые views
- [ ] Шаринг: роли «Администратор», «Руководитель» → пользователи / группы / workspace

### 6.3 Порядок гридов

1. Заказы
2. Диспозиция
3. Документы
4. Лиды, контрагенты, финансы

*Существующие role table presets не ломаем — views = слой поверх.*

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

---

## Открытые вопросы

- [ ] Точное время слотов «утро/вечер» (до 12:00 / после 12:00 или иное)
- [ ] Статус «в пути» — один enum или комбинация полей заказа
- [ ] Prod-домен для MCP: `crm.*` — финальный URL зафиксировать в `.env.example`
