# Управленческий учёт ↔ Бюджетирование (план vs факт)

Проект связи двух модулей: **план** из «Бюджетирования» и **факт** из «Управленческого учёта» с отображением **отклонений** за месяц / квартал / год.

**Связанные документы:**

- [`management-accounting-architecture.md`](./management-accounting-architecture.md) — факт, статьи, выписки
- [`management-accounting-implementation-plan.md`](./management-accounting-implementation-plan.md) — фазы M4–M5
- Модуль бюджетирования: `BudgetPlannerService`, `BudgetScenario`, `BudgetOpexArticle`, UI `Budgeting/Index.vue`

**Статус:** M5.2–M5.6 ✅, M4.3–M4.4 ✅. **Последнее обновление:** 2026-06-18

---

## Задача

| Вопрос | Ответ в UI |
| --- | --- |
| Сколько планировали потратить за период? | План из **зафиксированного** `BudgetPlanSnapshot` (или черновой opex, если снапшота нет) |
| Сколько фактически прошло по банку/разнесению? | Факт из управленческого учёта |
| Где отклонение? | Таблица «Отклонение от плана» — колонки план, факт, Δ, Δ % |
| План ФОТ продавцов выполняется? | Блок «ФОТ продавцов»: план из opex-статьи `payroll_managers` vs `management_payroll_half_users` |

---

## Иерархия планов

```
BudgetScenario (родитель, plan_type = company)
├── inputs: горизонт, маржа, manager_count, opex_articles…
├── BudgetPlanSnapshot — зафиксированный план по месяцам (кнопка «Зафиксировать план»)
└── BudgetScenario (дочерний, plan_type = sales_payroll)  ← backlog «План продавцов»
    ├── наследует manager_count, margin_per_manager из родителя
    ├── полупериоды 5 / 20
    ├── planned_accrual / planned_payout по user_id
    └── **план по продажам** по user_id (см. ниже)
```

**Реализовано:** снапшот родительского сценария. Дочерний сценарий, `budget_sales_half_users` и план по продажам — в backlog.

### План по продажам (расширение M5.3, зафиксировано 2026-07-09)

Помимо ФОТ по полупериодам, дочерний сценарий «Продавцы» должен включать **коммерческие планы** — чтобы сравнивать план/факт не только по зарплате, но и по результату продаж.

| Показатель | План (ввод) | Факт (из CRM) | Где смотреть |
| --- | --- | --- | --- |
| Выручка / маржа | ₽ или % от родительского сценария на `user_id` | Закрытые заказы за период | Управленка / дашборд руководителя (TBD) |
| Лиды / конверсия | Кол-во лидов или `won` на `user_id` | `leads` + статусы | Тот же блок план/факт |
| Сделки | Кол-во заказов или сумма ставки заказчика | `orders` по `order_owner_id` / `manager_id` | Тот же блок |

**Не путать:** это не замена KPI в compensation — отдельный слой бюджетирования «что планировали продать» vs «что продали». Таблица-черновик: `budget_sales_targets` (`scenario_id`, `user_id`, `period_month`, `metric`, `planned_value`). UI — в дочернем сценарии на `Budgeting/Index` или вкладка «План продавцов».

---

## Сопоставление статей

### Накладные (opex)

| Источник плана | Источник факта |
| --- | --- |
| `budget_plan_snapshot_lines` (фикс ₽/мес из снапшота) | `management_statement_lines` со статусом `allocated` |

**Связь:** `budget_plan_snapshot_lines.category_id` ↔ `management_expense_categories.id` через `budget_opex_articles.management_expense_category_id`.

В снапшот попадают только статьи с `cost_type = fixed_monthly` и `include_in_budget = true`. Статьи `% от маржи` — backlog.

### ФОТ продавцов (упрощённый v1)

| Показатель | План | Факт |
| --- | --- | --- |
| Выплата за период | Строка opex, привязанная к `payroll_managers` | Сумма `paid_amount` в `management_payroll_half_users` за пересекающиеся полупериоды |
| Начислено | — (в v1 только в блоке факта) | `accrued_amount` из salary sync |

---

## Периоды отчёта

Переключатель на `/finance/management-accounting` (вкладка «Учёт»):

| Режим | Границы | Агрегация плана |
| --- | --- | --- |
| Месяц | `YYYY-MM-01` … конец месяца | Сумма строк снапшота за месяц |
| Квартал | Q1–Q4 календарный | Сумма 3 месяцев |
| Год | `YYYY-01-01` … `YYYY-12-31` | Сумма месяцев внутри года |

**Источник плана:** последний `BudgetPlanSnapshot`, у которого `period_start` ≤ конец периода ≤ `period_end` и `approved_at` ≤ конец периода. Иначе — **live** opex из `budget_opex_articles` (предупреждение в UI).

Поля ответа аналитики: `plan_source` (`snapshot` | `live` | `none`), `plan_snapshot`, `variance_rows`, `payroll_variance`.

---

## Сервисный слой

```
BudgetPlanSnapshotService
  └── freeze(scenario, period_start, period_end, label, user)
  └── resolveSnapshotForPeriod(start, end)
  └── plannedByCategoryForPeriod(snapshot, start, end)

BudgetVarianceService
  └── compare(snapshot, start, end, categories, actualByCategory)
  └── payrollVariance(snapshot, start, end)

ManagementAccountingAnalyticsService
  └── build(period_type, anchor) — факт + план + variance_rows
```

**UI:**

| Экран | Компонент / действие |
| --- | --- |
| `Budgeting/Index.vue` | Секция «Зафиксировать план», список последних снапшотов |
| `ManagementAccounting/Index.vue` | `ManagementAccountingVarianceTable`, предупреждение при `plan_source: live` |
| `ManagementAccounting/ManualEntryModal` | Ручные операции (наличные) |
| `Reconcile.vue` | Split: чекбокс «Несколько заявок», `allocations[]` |

**Маршруты:**

- `POST budgeting/plan-snapshots` — freeze (`FreezeBudgetPlanSnapshotRequest`)
- `POST finance/management-accounting/manual-entries` — ручная операция
- `POST finance/management-accounting/lines/{line}/allocate` — в т.ч. `allocations: [{ payment_schedule_id, amount }]`

---

## Миграции

| Таблица | Назначение |
| --- | --- |
| `budget_plan_snapshots` | `scenario_id`, `period_label`, `period_start`, `period_end`, `approved_at`, `approved_by_user_id` |
| `budget_plan_snapshot_lines` | `snapshot_id`, `month`, `opex_article_id`, `category_id`, `planned_amount` |
| `budget_scenarios.parent_scenario_id` | Родитель для «План продавцов» (backlog) |
| `budget_scenarios.plan_type` | `company` \| `sales_payroll` |
| `management_statement_line_splits` | Split разнесения: несколько `payment_schedule_id` на одну строку выписки |
| `budget_opex_articles.management_expense_category_id` | FK на справочник управленки |

---

## Права

| Действие | Кто |
| --- | --- |
| Бюджетирование, фиксация плана | `belongs_to_management` / admin |
| Управленческий учёт, просмотр план/факт | `can_management_accounting` / admin |
| Ручные операции | `canAccessPaymentReconcile` |
| Разнос выписки | импортёр или admin |

---

## Решения (2026-06-18)

| Вопрос | Решение |
| --- | --- |
| Утверждение плана | **Ручная** кнопка «Зафиксировать план» с `period_label` (напр. «Q2 2026»). Авто-снапшот 1-го числа — backlog. |
| % opex от маржи в план/факт | **v1:** только фикс ₽/мес в снапшоте. % маржи — отдельная итерация. |
| Квартал/год | **Календарный** (янв–дек, Q1–Q4). |

---

## Backlog

| # | Задача |
|---|--------|
| M5.3 | Дочерний сценарий «План продавцов» + `budget_sales_half_users` (ФОТ по user_id) |
| M5.3b | **План по продажам** по user_id: `budget_sales_targets`, план/факт выручка·маржа·лиды·заказы |
| — | % от маржи в снапшоте и variance |
| — | Авто-freeze 1-го числа месяца |

---

## Тесты

| Файл | Что |
| --- | --- |
| `BudgetPlanSnapshotServiceTest` | freeze, resolve snapshot |
| `BudgetVarianceServiceTest` | compare по категориям |
| `ManagementAccountingAnalyticsServiceTest` | `plan_source: snapshot` |
| `ManagementAccountingAllocationSplitTest` | split на одну заявку |
