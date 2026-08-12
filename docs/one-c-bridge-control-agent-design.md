# Дизайн: агент-контролёр CRM ↔ 1С БП

> Статус: **реализация в коде 2026-08-11** (вертикальный срез).  
> Карточки: `docs/sync/v5-local-Components-OneC-BP-Connector.md`, `docs/management-accounting-architecture.md`.

## Env при выкате

```env
ONE_C_BRIDGE_ESCALATION_USER_ID={id ответственного}
# опционально: ONE_C_GROSS_BANK_ACCOUNT / ONE_C_PROFSFERA_BANK_ACCOUNT если счета другие
```

Счета CRM должны существовать: Гросс `40702810629940001726`, Профсфера `40702810508470000001` (создать/обновить на проде при выкате).

Команды: `one-c:bridge-check`, `management-accounting:pull-one-c-bank --company=gross --allocate --bridge-check`.
UI: виджет «Мост 1С» на УУ + «Запомнить правило» в Reconcile.
## 1. Цель

Держать мост CRM ↔ 1С в рабочем состоянии по трём юрлицам: банк разнесён, документы на месте, косяки не молчат. Агент — **надсмотрщик + оркестратор** поверх существующих сервисов; вердикт человеку одной кнопкой/виджетом: «всё в порядке» или список проблем + задача ответственному.

## 2. Решения (закрытые вопросы)

| # | Решение |
| --- | --- |
| 1 | Эскалация = **отчёт + задача** ответственному в CRM |
| 2 | Секреты OData **общие** (`ONE_C_USERNAME` / `ONE_C_PASSWORD`). Проверка 2026-08-11: Gross + ProSfera на `avtoalyns-crm` → **HTTP 200**, org «ГРОСС ООО» / «ПРОФСФЕРА ООО» |
| 3 | Поставка = **минимально-максимальный продукт**: не отдельный модуль, но **виджет-вердикт** на УУ («проверить мост» → OK / список дыр). Сразу банк + документы (реализации/сироты), не «только health без UI» |
| 4 | Эскалация **автоматическая** (cron/после pull): инициатор = **система**. Кнопка «Проверить» — страховка для ответственного: он тоже может быть эскалатором (создать/обновить задачу от своего имени) |

## 3. Сценарии

| Кто | Действие | Результат |
| --- | --- | --- |
| Бухгалтер / admin | Кнопка виджета «Проверить мост 1С» | Вердикт: платежи разнесены / документы на месте / что сломано |
| Cron | Health + эскалация | Отчёт; при проблемах — задача ответственному (без спама-дублей) |
| Бухгалтер | Pull банка по компании/периоду | `pull-one-c-bank --company=` + allocate с порогом |
| Агент / человек при разнесении | «Запомнить» keyword → статья/правило | `ManagementReconcileRuleService::remember` — **must have**, не «потом» |
| Clerk | Реализация из заказа | Как сейчас; если контрагента нет в 1С — **создать** (в scope) |
| Система (cron / после pull) | Обнаружила pending / 401 / сироту | Задача ответственному; initiator = system |
| Ответственный | Кнопка «Проверить» (страховка) | Тот же вердикт; при проблемах — задача, initiator = этот user |

## 4. As-is

- OData / реализации: `OneCBpClient`, `OneCRealizationSyncService`, `order_one_c_documents`
- Банк → УУ: `ManagementAccountingOneCPullService`, `pull-one-c-bank`
- Матчинг / remember: `ManagementAccountingMatchingService`, `ManagementReconcileRuleService`
- Сверка: `ContractorReconciliationService` (УПД-aware)
- MCP finance уже умеет allocate / suggest / remember rule
- Конфиг: одна `base_url`; нужно `publications[]`
- Gross/Profsfera: OData живой (общие creds OK). Org refs и счета Профсферы: Альфа `40702810508470000001`, ВТБ `40702810800810138669`.  
  **Риск:** фильтр `Date ge/lt` на этих ИБ падает с `AUTOORDER` даже без `$orderby` — в реализации нужен обход (другой предикат / выборка без Date + фильтр в PHP / уточнение у case-it). Автоальянс с тем же фильтром работает.

## 5. To-be

### 5.1 Сервисный слой

| Класс | Роль |
| --- | --- |
| `OneCPublicationCatalog` | autalliance / gross / profsfera → base_url, org_ref, bank_account_number (общие creds) |
| `OneCBridgeHealthService` | Вердикт по компаниям: odata_ok, pending, документы, last_error |
| `OneCBridgeEscalationService` | Дедуп-задачи **ответственному** (assignee из настроек); initiator = system \| user; ключ дедупа `(company, kind, period)` |
| `OneCCounterpartyEnsureService` | Найти по ИНН или **создать** в 1С (в scope) |
| Pull / Client | `--company`, override base_url/org; bank fetch **без** `$orderby`, если ИБ ругается |
| Matching + remember | Уже есть — агент/UI обязан предлагать «запомнить» после ручного разнесения |

LLM не считает деньги и не проводит документы в 1С.

Вердикт виджета (shape):

```text
{
  status: 'ok' | 'attention' | 'error',
  summary_ru: 'Всё в порядке: платежи разнесены, документы на месте.',
  companies: [{ code, odata_ok, pending_count, docs_gap_count, issues[] }],
  task_created: ?{ id, title }
}
```

### 5.2 API / MCP / Artisan

- `one-c:bridge-health` / `one-c:bridge-check` (то же, что кнопка виджета)
- `management-accounting:pull-one-c-bank --company=`
- MCP: `get_one_c_bridge_health`, `run_one_c_bridge_check`, `pull_one_c_bank` (dry_run→confirm), `list_one_c_reconcile_backlog`
- Remember keywords: расширить UX/агента вокруг уже существующего `remember_management_reconcile_rule` (must have в поставке)

### 5.3 UI (виджет, не отдельный продукт)

Место: `Finance/ManagementAccounting` (Index или шапка Reconcile) — компактный блок **«Мост 1С»**:

- Статус-светофор + одна фраза-вердикт
- Кнопка «Проверить»
- Раскрытие: по компаниям pending / документы / ошибка OData
- Ссылка в Reconcile с фильтром pending; факт создания задачи

Не заменяем экран разнесения — см. §6.

### 5.4 Данные

| Сущность | Правило |
| --- | --- |
| Банк | Канон = OData ИБ; XLS = fallback с пометкой |
| Creds | Общие env |
| Дедуп задач | Один открытый тикет на `(company, kind, period)` |
| Контрагент 1С | ensure before realization |
| ЭТрН / счета | Отдельный эпик, но **параллельный трек подготовки** (контракт полей, не блокировать мост) |

## 6. Уточнение «замена UI разнесения»

**Не предлагалось выкидывать Reconcile.** Имелось в виду: агент не рисует свой альтернативный экран разнесения и не дублирует `Reconcile.vue`. Человек по-прежнему разносит там; агент/виджет только вердикт, backlog и «запомнить правило». UI разнесения **остаётся**, его улучшаем точечно (кнопка remember), не переписываем.

## 7. Границы (обновлено)

**В scope (min-max поставка):**

- Мульти-ИБ pull + health + виджет-вердикт
- Эскалация: отчёт + задача ответственному
- Контроль документов: реализации / сироты / «заказ без дока при ожидании»
- Автосоздание контрагентов в 1С при sync реализации
- Обучение keywords/правил разнесения (remember) — must have в UX/агенте
- Подготовка контракта под ЭТрН/счета (design spike + места расширения), без полной реализации эпика в том же PR

**Вне scope до стабилизации косяков:**

- Автопроведение документов в 1С
- Двусторонний sync оплат 1С ↔ график без человека (платежи планируются вручную)

**Параллельный срочный эпик (не «забыть»):** ЭТрН / счета — готовить быстро; мост-агент не подменяет этот запуск, но не должен мешать (общие `OneCBpClient` / publication catalog).

## 8. RBAC

- Виджет / health / pull: `canAccessManagementAccounting` + admin
- Задачи эскалации: **assignee** = настраиваемый ответственный (user_id в config, напр. `ONE_C_BRIDGE_ESCALATION_USER_ID`); **initiator** = system (авто) или user (кнопка «Проверить»). Не путать initiator и assignee.
- Реализации + ensure counterparty: `canCreateOneCRealization` + mutate order
- MCP write: `mcp:write`

## 9. Фазы поставки (сжатые)

1. **Сейчас (один вертикальный срез):** catalog 3 ИБ + pull `--company` (fix orderby) + health/verdict service + виджет кнопка + задача при fail/pending + remember в потоке разнесения + ensure counterparty  
2. **Сразу следом:** контроль `order_one_c_documents` в том же вердикте; MCP tools  
3. **Параллельно:** spike ЭТрН/счета (поля, статусы, кто источник истины) — отдельный design/epic, deadline страны

## 10. Приёмка

1. Кнопка «Проверить мост» на Автоальянсе даёт вердикт, согласованный с Reconcile (pending counts).  
2. Gross/Profsfera pull по OData в свои счета CRM; общие creds.  
3. При pending/auth fail создаётся **одна** задача ответственному (повторный check не плодит дубли).  
4. Ручное разнесение → доступно «запомнить» keyword/правило; следующее попадание матчится.  
5. Реализация: нет контрагента в 1С → создаётся, затем документ.  
6. Нет автопроведения Posted в 1С.  
7. Виджет живёт внутри УУ, без нового пункта меню «продукт 1С».

## 11. Тесты

- Catalog + client без orderby на «строгой» ИБ  
- Health verdict ok/attention/error  
- Escalation dedup задач  
- Ensure counterparty: find / create  
- Remember rule round-trip  
- RBAC виджета / MCP  
- Регрессия Autalliance allocate keywords

## 12. Эскалация (закрыто)

| | Авто (основной путь) | Кнопка «Проверить» |
| --- | --- | --- |
| Когда | cron / после pull | вручную, страховка |
| Initiator | система | ответственный (кто нажал) |
| Assignee | всегда **ответственный** из config (`ONE_C_BRIDGE_ESCALATION_USER_ID`) | тот же assignee |
| Дедуп | не плодить вторую открытую задачу на тот же `(company, kind, period)` | то же |

Конкретный user_id ответственного — в `.env` при выкате (бухгалтер/РОП по выбору заказчика), не зашивать в код.

---

## Инварианты

1. Компания → своя публикация и свой счёт CRM.  
2. Авторазнесение только при conf ≥ порога.  
3. Проведённое в 1С не удаляем из CRM.  
4. Расхождение → задача, не «подкрутить» заказ.  
5. После OData у Гросс/Профсфера XLS только fallback.  
6. Обучение правил — явное действие человека (или подтверждённый агентский remember), не молчаливый LLM.
