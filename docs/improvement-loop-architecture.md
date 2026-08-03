# Контур улучшений (Improvement Loop) — продуктовый контур L0–L3

**Статус:** L0–L5 в коде (2026-08-03) · **Домен v1+:** продажи + документы/оплаты + флот + УУ · **Дата:** 2026-08-03  
**Связано:** [commercial-intelligence-roadmap.md](./commercial-intelligence-roadmap.md), [ai-platform-architecture.md](./ai-platform-architecture.md), карточка `docs/sync/v5-local-Components-Improvement-Loop.md`.

---

## 1. Цель

Единый инструмент, в котором **оркестратор + субагенты**:

1. **смотрят** на деятельность (сигналы из CRM);
2. **диагностируют** узкие места;
3. **выдвигают гипотезы** улучшений;
4. **проводят эксперименты** (сначала ручной A/B v0);
5. **мерят** результат и **закрепляют** победителя (HITL).

Это не чат с LLM и не n8n: **машина состояний + UI «Улучшения»**, LLM только там, где нужна генерация/сжатие.

---

## 2. Не делаем / отложено

- Автопатч скриптов / БП / ролей без человека.
- Полный sequential analysis / Bayesian early stop — в L4 только **подсказка** HITL по z-тесту + мощности.
- Другие домены (УУ, флот, документы) — **L5+**.
- Отдельный Python/Django сервис, внешние оркестраторы.
- WebSocket «ТОП-1 на дашборд» — достаточно inbox / страница модуля.

---

## 3. Петля (канон)

```text
Observe → Diagnose → Propose → Experiment → Measure → Decide → Memory
   ↑                                                         │
   └────────────────── следующий цикл ◄──────────────────────┘
```

| Состояние сущности | Смысл |
| --- | --- |
| `signal` | Что увидели (без LLM или с агрегатом) |
| `hypothesis` | Что предлагаем изменить |
| `experiment` | Как проверяем (A vs B, период, когорта) |
| `verdict` | Итог: adopt / reject / inconclusive |
| `adoption` | Что закрепили в продукте (скрипт / playbook / правило) |

---

## 4. Сущности (БД)

Префикс таблиц: `improvement_`.

### 4.1 `improvement_signals`

Сырой или агрегированный сигнал Observe/Diagnose.

| Поле | Тип | Комментарий |
| --- | --- | --- |
| `id` | PK | |
| `domain` | string | v1: `sales` |
| `kind` | string | напр. `loss_flag_spike`, `idle_qualification`, `win_rate_drop` |
| `severity` | string | `info` / `warn` / `critical` |
| `title` | string | коротко для UI |
| `payload` | json | counts, sample lead ids (без ПД в LLM) |
| `period_from` / `period_to` | date | окно наблюдения |
| `source` | string | `rules` / `coaching` / `pipeline` |
| `status` | string | `open` / `linked` / `dismissed` |
| `created_at` | | |

### 4.2 `improvement_hypotheses`

| Поле | Тип | Комментарий |
| --- | --- | --- |
| `id` | PK | |
| `signal_id` | FK nullable | от какого сигнала |
| `category` | string | `price` / `script` / `channel` / `process` |
| `text` | text | формулировка гипотезы |
| `short_reason` | text | почему против болей |
| `impact` / `confidence` / `ease` | tinyint | ICE 1–10 |
| `score` | decimal | `(I+C)/E` |
| `status` | string | `draft` / `accepted` / `rejected` / `in_experiment` / `adopted` / `archived` |
| `source` | string | `llm_pipeline` / `manual` |
| `pipeline_run_id` | nullable | прогон оркестратора |
| `created_by` | FK users nullable | |
| timestamps | | |

### 4.3 `improvement_experiments`

Ручной A/B v0 (не рандомизация движком).

| Поле | Тип | Комментарий |
| --- | --- | --- |
| `id` | PK | |
| `hypothesis_id` | FK | |
| `name` | string | |
| `status` | string | `planned` / `running` / `completed` / `cancelled` |
| `variant_a` | json | контроль: описание / script_node_id / playbook note |
| `variant_b` | json | тест |
| `metric_key` | string | `win_rate` / `stage_to_offer` / `reply_rate` |
| `starts_on` / `ends_on` | date | |
| `cohort` | json | `{ user_ids: [], department_id? }` — кто в A/B |
| `result_snapshot` | json nullable | агрегаты на момент вердикта |
| `verdict` | string nullable | `adopt_b` / `keep_a` / `inconclusive` |
| `verdict_note` | text nullable | |
| `decided_by` / `decided_at` | | |
| timestamps | | |

### 4.4 `improvement_adoptions` (L3)

| Поле | Тип | Комментарий |
| --- | --- | --- |
| `id` | PK | |
| `experiment_id` | FK | |
| `hypothesis_id` | FK | |
| `target_type` | string | `sales_script_node` / `bp_playbook` / `sales_book_article` / `manual_note` |
| `target_id` | nullable | |
| `summary` | text | что изменили |
| `adopted_by` | FK | |
| `adopted_at` | | |

### 4.5 Опционально: `improvement_pipeline_runs`

Лог прогона Propose (cron): статус, длительность, counts, error summary. Детали LLM — в `ai_interaction_events` (не плодить `ai_errors`).

---

## 5. Оркестратор и субагенты

Один дирижёр: `ImprovementLoopOrchestrator` (сервисный слой).  
Субагенты — **методы / отдельные Agent-классы** `laravel/ai` со structured output, не отдельные OpenAI-подключения.

| Роль | Класс / метод | Слой | LLM? |
| --- | --- | --- | --- |
| **Наблюдатель** | `ImprovementSignalCollector` | Observe | Нет (правила + существующие сервисы) |
| **Диагност** | часть collector + группировка | Diagnose | Нет в L0; опц. сжатие в L1 |
| **Археолог** | `HypothesisArchaeologistAgent` | Propose | Да — боли из close_outcome / notes |
| **Стратег** | `HypothesisStrategistAgent` | Propose | Да — 15 гипотез |
| **Критик** | `HypothesisCriticAgent` | Propose | Да — ≤5 «внедрить завтра» |
| **Метрик ICE** | `HypothesisMetricAgent` | Propose | Да — ICE + топ |
| **Экспериментатор** | `ImprovementExperimentService` | Experiment | Нет |
| **Измеритель** | `ImprovementExperimentMetricsService` | Measure | Нет |
| **Внедритель** | `ImprovementAdoptionService` | Decide/Adopt | Нет (HITL UI) |

Промпты Археолог/Стратег/Критик/Метрик — адаптировать из ТЗ; хранить в `app/Ai/Prompts/ImprovementLoop/` или в Agent `instructions()`.

**Политика AI:** только через `AiRequestGate` + redaction; во внешний LLM — обезличенные формулировки отказов и агрегаты, без ФИО/телефонов/сумм сделок как ПД. Канал feature: `improvement_loop`.

---

## 6. Фазы реализации

### L0 — Observe + Diagnose (без LLM)

**Цель:** страница «Улучшения» показывает живые сигналы из уже существующих данных.

**Входы (reuse):**

- `ManagerSalesCoachingInsightsService` / `ManagerDealSignalExtractor`
- `LeadCloseOutcomeFlagCatalog`, lost/won за период
- опционально агрегаты из отчёта менеджеров (`ManagerTeamReportService`) — только read

**Код:**

| Зона | Файлы (ориентир) |
| --- | --- |
| Feature flag | `config/crm_features.php` → `improvement_loop` |
| RBAC | `RoleAccess` + область `reports` или узкая `improvement_loop` (решить при коде: старт = `reports` + supervisor/admin) |
| Collector | `app/Services/Improvement/ImprovementSignalCollector.php` |
| Persist | model + migration `improvement_signals` |
| Command | `improvement:collect-signals` (daily) |
| UI | `resources/js/Pages/Improvement/Index.vue` — вкладка «Сигналы» |
| Routes | `improvement.*` + пункт меню (visibility) |
| Tests | Feature: collector пишет сигналы; gate без доступа → 403 |

**DoD L0:**

- [ ] Cron/artisan собирает сигналы за N дней без падений на пустых данных.
- [ ] Руководитель с доступом видит список сигналов (title, severity, payload counts).
- [ ] Можно dismiss сигнала.
- [ ] PHPUnit на collector + HTTP index.
- [ ] LLM не вызывается.

---

### L1 — Propose (гипотезы из ТЗ)

**Цель:** pipeline Археолог→…→Метрик пишет `improvement_hypotheses`; HITL принять/отклонить.

**Код:**

| Зона | Файлы |
| --- | --- |
| Orchestrator | `ImprovementHypothesisPipeline` / `run_pipeline()` |
| Agents | `app/Ai/Agents/Improvement/*` + structured schemas |
| Command | `improvement:run-hypothesis-pipeline` (daily после collect) |
| UI | вкладка «Гипотезы»: score, category, accept/reject |
| Link | hypothesis ↔ signal |
| Tests | Agent::fake + pipeline на fixture lost-лидов |

**DoD L1:**

- [ ] При наличии lost-лидов с close_outcome появляются draft-гипотезы.
- [ ] Ошибка LLM не роняет cron (retry + запись в `ai_interaction_events` / pipeline_runs).
- [ ] Accept/reject меняют status; rejected не предлагаются в эксперимент.
- [ ] Промпты правятся в одном месте без правки оркестратора.

---

### L2 — Experiment v0 (ручной A/B)

**Цель:** из accepted-гипотезы создать эксперимент: вариант A/B, период, когорта менеджеров, метрика.

**Код:**

| Зона | Файлы |
| --- | --- |
| Service | `ImprovementExperimentService` |
| Metrics | `ImprovementExperimentMetricsService` (win_rate по closed leads когорты за период) |
| UI | вкладка «Эксперименты»: создать / start / complete |
| Messenger | уведомление куратору при start/complete (встроенный мессенджер, если есть паттерн) |

**DoD L2:**

- [ ] Эксперимент нельзя стартовать без accepted hypothesis.
- [ ] На complete считается snapshot метрики A vs B (по `cohort.user_ids` и периоду).
- [ ] Вердикт `adopt_b` / `keep_a` / `inconclusive` + note обязателен при complete.
- [ ] Нет автоматического изменения скриптов.

**Метрика v0:** `win_rate` = won / closed у пользователей когорты за `starts_on`…`ends_on`. Остальные metric_key — stub или backlog.

---

### L3 — Decide + Adopt + Memory

**Цель:** после `adopt_b` — зафиксировать внедрение и закрыть петлю.

**Код:**

| Зона | Файлы |
| --- | --- |
| Adoption | `ImprovementAdoptionService` |
| Targets v1 | (a) создать задачу руководителю с текстом гипотезы; (b) опц. deep-link в редактор скрипта; (c) запись `improvement_adoptions` |
| Memory | при Propose — исключать/понижать гипотезы с тем же fingerprint, что уже `rejected`/`keep_a` за 90 дней |
| UI | «Закреплено» + история вердиктов |

**DoD L3:**

- [x] `adopt_b` создаёт adoption + (минимум) задачу или заметку «внедрить».
- [x] Hypothesis → `adopted`; experiment → `completed`.
- [x] Повторный pipeline не спамит идентичными draft без изменений сигналов.
- [x] Карточка в sync / handoff обновлена.

---

### L4 — Рандомизация лидов + статистика + следующий цикл

**Цель:** эксперимент назначает варианты на **лиды** (стабильный hash), считает значимость win rate, подсказывает early-stop; после вердикта пишет сигнал `experiment_outcome`.

| Зона | Файлы |
| --- | --- |
| Assignments | `improvement_experiment_assignments`, `ImprovementExperimentAssignmentService` |
| Observer | `LeadImprovementExperimentObserver` (won/lost → sync) |
| Stats | `ImprovementAbStatistics` (z-тест, Wilson CI, required n, early_stop_suggested) |
| Metrics | `ImprovementExperimentMetricsService` — mode `leads` \| `managers` |
| Next cycle | `ImprovementNextCycleService` |
| UI | режим назначения + live Δ / p / мощность |

**DoD L4:**

- [x] `assignment_mode=leads` + `pool_user_ids`; hash(experiment|lead) → a|b.
- [x] Snapshot содержит `stats.significant`, `required_n_per_arm`, `early_stop_suggested` (HITL, без авто-complete).
- [x] Complete → сигнал следующего цикла.
- [x] PHPUnit L4.

**Не в L4:** автопатч скрипта без HITL; Bayesian/seq analysis; «магическое» изменение узлов без выбора.

---

### L5 — Мультидомен + HITL в скрипт + MCP

| Возможность | Реализация |
| --- | --- |
| Сигналы documents | просроченные `payment_schedules` |
| Сигналы fleet | `fleet_trips` long `planned` |
| Сигналы finance | pending `management_statement_lines` |
| HITL в скрипт | `applyToScriptNode` → `body_variant_b` + `ab_enabled` (основной body не трогаем) |
| MCP | `get_improvement_loop_insights` |

**DoD L5:** покрыто тестами `ImprovementLoopL5Test` + registry MCP.

**Всё ещё вне scope:** silent overwrite `body`, Bayesian early-stop, автосоздание узлов.

---

## 7. UI «Улучшения»

Одна страница Inertia, вкладки:

1. **Сигналы** (L0)
2. **Гипотезы** (L1)
3. **Эксперименты** (L2)
4. **История** (L3: verdicts + adoptions)

Меню: **Планирование → Улучшения** (`/improvement`).  
Не дублировать полный coaching dashboard — smart-link «открыть в коучинге» при необходимости.

---

## 8. Расписание

```text
ежедневно:
  improvement:collect-signals
  improvement:run-hypothesis-pipeline   # L1+; можно --dry-run
```

Полный цикл Propose — async job (очередь), не жёсткий SLA «&lt;15 сек».

---

## 9. Соответствие исходному ТЗ

| ТЗ | Куда легло |
| --- | --- |
| 4 агента + ICE | L1 Propose |
| `ai_hypotheses` | `improvement_hypotheses` |
| Cron | L0+L1 commands |
| Страница «Советник» | UI «Улучшения» |
| «Применить → suggested_script» | **заменено** на Experiment → Adopt HITL |
| A/B «и так далее» | L2–L3 (v0), строгий A/B = L4+ |
| `communications` / call_transcripts | **не используем**; вход = leads close_outcome + coaching signals (+ mail позже) |

---

## 10. Порядок работ в коде

1. Миграции + models + feature flag + пустой Index (вкладки-заглушки).
2. **L0** collector + UI сигналов + тесты → smoke.
3. **L1** pipeline + agents + UI гипотез + тесты.
4. **L2** experiments + metrics win_rate.
5. **L3** adoption + memory fingerprint.
6. Документация: карточка `docs/sync/v5-local-Components-Improvement-Loop.md`, handoff.

Оценка грубо: L0 ~0.5–1 дн., L1 ~1.5–2 дн., L2 ~1 дн., L3 ~0.5–1 дн. (без полировки UX).

---

## 11. Открытые решения (зафиксировать при старте кода)

1. RBAC: новая область `improvement_loop` или только `reports` + admin/supervisor?
2. Adoption v1: только задача, или сразу правка узла скрипта из UI?
3. Нужен ли MCP tool `list_improvement_*` в L1 или после L2?

**Рекомендация по умолчанию:** (1) `reports` + admin на старте, область вынести позже; (2) задача + manual_note; (3) MCP после L2.
