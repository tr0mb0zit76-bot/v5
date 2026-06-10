# Управленческий учёт ↔ Бюджетирование (план vs факт)

Проект связи двух модулей: **план** из «Бюджетирования» и **факт** из «Управленческого учёта» с отображением **отклонений** за месяц / квартал / год.

**Связанные документы:**

- [`management-accounting-architecture.md`](./management-accounting-architecture.md) — факт, статьи, выписки
- Модуль бюджетирования: `BudgetPlannerService`, `BudgetScenario`, `BudgetOpexArticle`, UI `Budgeting/Index.vue`

**Статус:** частичная реализация (M5.1 ✅, черновик план/факт на Index). **Последнее обновление:** 2026-06-11

---

## Задача

| Вопрос | Ответ в UI |
| --- | --- |
| Сколько планировали потратить/получить за период? | План из бюджета (родительский сценарий) |
| Сколько фактически прошло по банку/разнесению? | Факт из управленческого учёта |
| Где отклонение? | Колонка Δ и % по каждой статье |
| План ФОТ продавцов выполняется? | Дочерний план «Продавцы» vs `management_payroll_half_users` |

---

## Иерархия планов

```
BudgetScenario (родитель, «Бюджет компании»)
├── inputs: горизонт, маржа, manager_count, opex_articles…
├── BudgetPlanSnapshot — зафиксированный план по месяцам (при «утверждении»)
└── BudgetScenario (дочерний, type = sales_payroll)  ← «План продавцов»
    ├── наследует manager_count, margin_per_manager из родителя
    ├── полупериоды 5 / 20 (как в управленке)
    └── planned_accrual / planned_payout по user_id
```

**Правило:** дочерний сценарий **не редактируется вручную** по суммам — пересчитывается из родителя (кнопка «Обновить план продавцов» или автоматически при сохранении родителя).

---

## Сопоставление статей

### Накладные (opex)

| Источник плана | Источник факта |
| --- | --- |
| `budget_opex_articles` (фикс ₽/мес или % маржи) | `management_statement_lines` со статусом `allocated` |

**Связь:** поле `management_expense_category_id` на `budget_opex_articles` (nullable). Заполняется `ManagementExpenseCategorySyncService` при sync (`code = budget_opex_{id}`).

Примеры маппинга при сиде/настройке:

| Статья бюджета | `management_expense_categories.code` |
| --- | --- |
| Банковское обслуживание | `bank_fees` |
| АТИ, лицензии | `services_other` |
| Аренда, связь | `cash_other_out` или отдельная пользовательская статья |

### Операционный контур

План маржи / выручки из `BudgetPlannerService::buildPlan()`:

- **План поступлений от заказчиков** ≈ сумма маржи + плановые расходы перевозчиков (упрощённо: целевая маржа × объём)
- **Факт** — сумма `allocation_type=operational`, `direction=in` / `out` за период

Детализацию по заявкам оставляем в графике оплат; в управленке — **агрегат по периоду**.

### ФОТ продавцов (дочерний план)

| Показатель | План (дочерний) | Факт (управленка) |
| --- | --- | --- |
| Начислено за полупериод | `budget_sales_half_users.planned_accrued` | `management_payroll_half_users.accrued_amount` (из salary) |
| Выплачено | `budget_sales_half_users.planned_paid` | `management_payroll_half_users.paid_amount` (из банка) |

Формула плана начислений (черновик): из родительского `margin_per_manager` × `manager_count` × доля ФОТ в настройках, либо явная строка opex «ФОТ продавцы».

---

## Периоды отчёта

Единый переключатель на странице управленческого учёта:

| Режим | Границы | Агрегация плана |
| --- | --- | --- |
| Месяц | `YYYY-MM-01` … конец месяца | Сумма месячных значений снапшота |
| Квартал | Q1–Q4 календарный | Сумма 3 месяцев |
| Год | `YYYY-01-01` … `YYYY-12-31` | Сумма 12 месяцев или `horizon_months` сценария |

План берётся из **последнего утверждённого** `BudgetPlanSnapshot` на дату, не из «живого» черновика сценария (чтобы сравнение было стабильным).

---

## Сервисный слой (целевая реализация)

```
BudgetPlanSnapshotService
  └── freeze(scenario, period) → budget_plan_snapshots + lines

ManagementAccountingActualsService
  └── byCategory(period) → fact amounts from management_statement_lines
  └── payrollByHalf(period) → from management_payroll_half_users

BudgetVarianceService
  └── compare(snapshotId, period, granularity)
      → [{ category_id, name, planned, actual, variance, variance_percent }]
```

**UI (фаза M5):** блок «Отклонение от плана» на `Finance/ManagementAccounting/Index.vue`:

- таблица статей;
- подтаблица «ФОТ продавцов» (план дочернего сценария vs факт);
- фильтр месяц / квартал / год.

---

## Миграции (черновик)

| Таблица | Назначение |
| --- | --- |
| `budget_plan_snapshots` | Версия плана: `scenario_id`, `period_type`, `approved_at`, `approved_by` |
| `budget_plan_snapshot_lines` | `snapshot_id`, `month`, `opex_article_id`, `category_id`, `planned_amount` |
| `budget_scenarios.parent_scenario_id` | Родитель для «План продавцов» |
| `budget_scenarios.plan_type` | `company` \| `sales_payroll` |
| `budget_sales_half_users` | `snapshot_id`, `payroll_half_id`, `user_id`, `planned_accrued`, `planned_paid` |
| `budget_opex_articles.management_expense_category_id` | FK на справочник управленки |

---

## Права

| Модуль | Кто |
| --- | --- |
| Бюджетирование | `belongs_to_management` / admin |
| Управленческий учёт | `can_management_accounting` / admin |
| Блок «План vs факт» | пересечение **или** только управленка с read-only планом (решение: показывать план всем с доступом к управленке, редактировать план — только бюджет) |

---

## Фазы внедрения (дополнение к M4)

| # | Задача |
|---|--------|
| M5.1 | `management_expense_category_id` на opex + сиды связей |
| M5.2 | Снапшот плана (`BudgetPlanSnapshotService`) |
| M5.3 | Дочерний сценарий «План продавцов» + пересчёт полупериодов |
| M5.4 | `ManagementAccountingActualsService` + `BudgetVarianceService` |
| M5.5 | UI отклонений на Index управленки |
| M5.6 | Тесты агрегации и variance |

---

## Открытые вопросы

- [ ] Утверждение плана: одна кнопка «Зафиксировать на квартал» или авто-снапшот 1-го числа?
- [ ] % от маржи в opex: факт маржи брать из заказов или из операционных поступлений в управленке?
- [ ] Квартал/год: скользящий или календарный?
