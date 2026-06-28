# План внедрения модуля «Управленческий учёт»

Документ задаёт **последовательность работ** и **критерии готовности**. Модуль размещается в разделе **Финансы**.

**Архитектура:** [management-accounting-architecture.md](./management-accounting-architecture.md).

**План vs факт:** [management-accounting-budgeting-integration.md](./management-accounting-budgeting-integration.md).

**Стек:** Laravel 13, Inertia v2, Vue 3, PHPUnit; парсер XLSX без новых зависимостей.

---

## Принципы

1. Два контура: **операционный** (заявки / график оплат) и **управленческий** (всё остальное).
2. Справочник статей — **плоский**, ~8–10 строк, редактируемый; не копировать план счетов 1С.
3. Выписку загружает и разносит назначенный сотрудник; ручной ввод — все с доступом к разносу.
4. ФОТ продавцов: полупериоды **5** и **20**, связь с `salary_accruals` / выплатами из банка.

---

## Фаза M0. Каркас и права ✅ (2026-06-11)

| # | Задача | Статус |
|---|--------|--------|
| M0.1 | Миграции: `can_management_accounting`, `management_*` таблицы | ✅ |
| M0.2 | Модели, сиды счетов и статей | ✅ |
| M0.3 | `RoleAccess::canAccessManagementAccounting()`, галка в Users | ✅ |
| M0.4 | Маршруты, меню «Финансы», плитка на `/finance` | ✅ |

---

## Фаза M1. Импорт и парсер ✅

| # | Задача | Статус |
|---|--------|--------|
| M1.1 | `SberRegistryXlsxParser` (`sber_registry_v1`) | ✅ |
| M1.2 | `ManagementAccountingImportService`, дедуп `line_hash` | ✅ |
| M1.3 | UI загрузки на Index | ✅ |
| M1.4 | Unit-тест парсера | ✅ |

---

## Фаза M2. Матчинг и разнесение ✅

| # | Задача | Статус |
|---|--------|--------|
| M2.1 | `ManagementAccountingMatchingService` (заявка, комиссии, ФОТ) | ✅ |
| M2.2 | Экран разнесения `Reconcile.vue` | ✅ |
| M2.3 | `ManagementAccountingAllocationService` → график оплат + ledger | ✅ |
| M2.4 | Feature-тест прав доступа | ✅ |

---

## Фаза M3. ФОТ полупериоды ✅

| # | Задача | Статус |
|---|--------|--------|
| M3.1 | `ManagementPayrollHalfCalendar` (5 / 20) | ✅ |
| M3.2 | `ManagementPayrollHalfService`, sync из `salary_accruals` | ✅ |
| M3.3 | Учёт выплат из банка в `management_payroll_half_users` | ✅ |
| M3.4 | Unit-тест календаря | ✅ |

---

## Фаза M5. План vs факт (Бюджетирование) ✅ (2026-06-18)

См. [`management-accounting-budgeting-integration.md`](./management-accounting-budgeting-integration.md).

| # | Задача | Статус |
|---|--------|--------|
| M5.0 | Архитектура связи, снапшоты, дочерний план «Продавцы» | 📋 дочерний план — backlog |
| M5.1 | Маппинг opex → `management_expense_categories` | ✅ sync + `management_expense_category_id` |
| M5.2 | `BudgetPlanSnapshotService::freeze()` + миграции снапшотов | ✅ |
| M5.3 | Дочерний сценарий «План продавцов» | backlog (v1: агрегат ФОТ в variance) |
| M5.4 | `BudgetVarianceService` + интеграция в `ManagementAccountingAnalyticsService` | ✅ |
| M5.5 | UI отклонений (`ManagementAccountingVarianceTable`) + freeze на `Budgeting/Index` | ✅ |
| M5.6 | Тесты: `BudgetPlanSnapshotServiceTest`, `BudgetVarianceServiceTest`, analytics snapshot | ✅ |

---

## Фаза M4. Улучшения

| # | Задача | Статус |
|---|--------|--------|
| M4.0 | Справочник статей: добавление, sync с бюджетом, бейджи source | ✅ |
| M4.0b | MCP tools управленки + `management_reconcile_rules` (обучение) | ✅ |
| M4.1 | Матчинг по 24-значному UID из 1С в назначении платежа | backlog |
| M4.2 | Матчинг по номерам счетов (наши / подрядчиков) из заявки | backlog |
| M4.3 | Split: несколько переводов на одну заявку с UI распределения сумм | ✅ `management_statement_line_splits`, Reconcile |
| M4.4 | Ручные операции (наличные) — форма на Index | ✅ `ManagementAccountingManualEntryModal` |
| M4.5 | CNY: курс на дату операции | backlog |
| M4.6 | Отчёт «полная картина»: операционный + управленческий за период | ✅ `ManagementAccountingFullPictureService` + блок на Index |
| M4.7 | Второй банк / другие форматы выписок | backlog |

---

## Definition of Done (пилот M0–M3)

- [x] Пользователь с флагом загружает XLSX Сбера и видит список операций.
- [x] Система предлагает операционные совпадения по номеру заявки.
- [x] Подтверждение операционной строки обновляет график оплат и журнал событий.
- [x] ФОТ: видны начислено/выплачено за текущий полупериод.
- [x] Документация и индексы в репозитории и Hive Mind.

## Definition of Done (спринт план/факт + наличные + split)

- [x] Руководитель фиксирует план в «Бюджетировании» — снапшот не меняется при правках черновика.
- [x] На Index управленки — таблица отклонений по статьям и блок ФОТ за период.
- [x] Ручная операция (наличные) без выписки попадает в учёт.
- [x] Один платёж можно разнести на несколько строк графика (split).
- [x] PHPUnit на снапшоты, variance, split.

---

*Версия плана: 1.3 (2026-06-18). M5.2–M5.6, M4.3, M4.4 — реализованы в текущей ветке.*
