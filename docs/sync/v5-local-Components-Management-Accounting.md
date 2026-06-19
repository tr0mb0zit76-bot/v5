---
id: component-management-accounting
type: component
status: canon
name: "Management Accounting"
componentType: service
tags: [finance, accounting, budget, reconcile]
---

# Management Accounting (Управленческий учёт)

Банковские выписки, наличные, ФОТ и прочие статьи — **поверх** операционного ДДС по заявкам.

## Доступ

- URL: `/finance/management-accounting`
- Флаг: `users.can_management_accounting` (+ admin)
- Разнос выписки: импортёр или admin
- Ручные операции: `canAccessPaymentReconcile`

## Backend

- Импорт: `ManagementAccountingImportService`, `SberRegistryXlsxParser`
- Матчинг: `ManagementAccountingMatchingService`, `ManagementReconcileRuleService`
- Разнесение: `ManagementAccountingAllocationService` (в т.ч. **split** → `management_statement_line_splits`)
- Аналитика: `ManagementAccountingAnalyticsService`
- План/факт: `BudgetPlanSnapshotService`, `BudgetVarianceService`
- ФОТ 5/20: `ManagementPayrollHalfCalendar`, `ManagementPayrollHalfService`
- MCP: `ManagementAccountingMcpService`

## Бюджетирование (связь)

- URL: `/budgeting` · `belongs_to_management`
- Фиксация плана: `POST /budgeting/plan-snapshots` → `budget_plan_snapshots` + `budget_plan_snapshot_lines`
- План в аналитике: последний снапшот на период; fallback — черновой opex (`plan_source: live`)

## Frontend

- `Finance/ManagementAccounting/Index.vue` — вкладки «Учёт» / «Статьи», variance, ручные операции
- `Finance/ManagementAccounting/Reconcile.vue` — разнесение, split на несколько заявок
- `Components/Finance/ManagementAccountingVarianceTable.vue`
- `Components/Finance/ManagementAccountingManualEntryModal.vue`
- `Budgeting/Index.vue` — кнопка «Зафиксировать план»

## Таблицы (ключевые)

- `management_statement_lines`, `management_statement_line_splits`
- `management_expense_categories`, `management_payroll_half_users`
- `budget_plan_snapshots`, `budget_plan_snapshot_lines`

## Документация в git

- `docs/management-accounting-architecture.md`
- `docs/management-accounting-implementation-plan.md`
- `docs/management-accounting-budgeting-integration.md`

*Обновлено: 2026-06-18.*
