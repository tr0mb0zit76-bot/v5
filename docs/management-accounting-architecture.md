# Управленческий учёт (архитектура)

Техническая карта модуля в разделе **Финансы** (2026-06).

**План внедрения:** [`management-accounting-implementation-plan.md`](./management-accounting-implementation-plan.md).

**Связанный операционный контур:** [`payment-schedule-architecture.md`](./payment-schedule-architecture.md) (график оплат по заявкам).

**Последнее обновление:** 2026-06-11

---

## Назначение

Полная финансовая картина компании: банковские выписки, наличные, ФОТ и прочие статьи — **поверх** операционного ДДС по заказам.

| Контур | Содержание | Куда разносится |
| --- | --- | --- |
| **Операционный** | Оплаты по перевозкам (заказчики, перевозчики) | `payment_schedules` + `payment_schedule_payment_events` |
| **Управленческий** | Банк, комиссии, услуги/лицензии, ФОТ, наличные, прочее | `management_statement_lines` + справочник статей |

Термин **«Операционный»** (не «производственный»).

---

## Доступ

| Кто | Условие |
| --- | --- |
| Admin | всегда |
| Пользователь | флаг `users.can_management_accounting` (галка в карточке пользователя) |

Проверка: `RoleAccess::canAccessManagementAccounting()`.

Бюджетирование (`belongs_to_management`) — **отдельный** модуль, права не пересекаются.

Выписку разносит **тот, кто загрузил** (на экране разнесения — только импортёр или admin).

---

## Модель данных

### `management_bank_accounts`

Справочник счетов (сиды: Сбер *1997, банк 2 RUB/CNY).

| Поле | Смысл |
| --- | --- |
| `account_number` | Полный номер р/с (уникальный) |
| `account_mask` | Маска для UI (`****1997`) |
| `currency` | `RUB` / `CNY` |

### `management_expense_categories`

Плоский редактируемый справочник (~10 системных статей, сиды).

| `code` | Название | `kind` |
| --- | --- | --- |
| `operational_customer_in` | Оплата от заказчика | `operational_in` |
| `operational_carrier_out` | Оплата перевозчику | `operational_out` |
| `bank_fees` | Банковские комиссии и сборы | `overhead` |
| `services_other` | Услуги и лицензии (прочее) | `overhead` |
| `payroll_accrued_sales` | ФОТ продавцы (начислено) | `payroll_accrued` |
| `payroll_paid_sales` | ФОТ продавцы (выплачено) | `payroll_paid` |
| `payroll_other` | ФОТ прочие | `payroll_other` |
| `cash_other_in` / `cash_other_out` | Наличные / прочие | `cash` |
| `unclassified` | Неразнесённое | `unclassified` |

**Одна статья** на все банковские комиссии и **одна** на услуги/лицензии (АТИ и т.п.).

### `management_statement_imports`

Пакет загрузки выписки.

| Поле | Смысл |
| --- | --- |
| `format` | `sber_registry_v1` |
| `imported_by` | Кто загрузил |
| `status` | `draft` / `reconciled` |
| `lines_count`, `lines_allocated` | Прогресс разнесения |

### `management_statement_lines`

Одна банковская (или ручная) операция.

| Поле | Смысл |
| --- | --- |
| `line_hash` | Дедупликация в рамках счёта |
| `direction` | `in` / `out` |
| `status` | `pending` / `allocated` |
| `match_type` | `operational` / `payroll` / `category` |
| `suggested_*` | Автоподсказки матчинга |
| `allocation_*` | Итог разнесения |

### `management_payroll_halves` + `management_payroll_half_users`

Полупериоды ФОТ продавцов:

| Полупериод | Дни работы | Дата выплаты |
| --- | --- | --- |
| 1 | 1–15 текущего месяца | 20-е того же месяца |
| 2 | 16 — последний день месяца | 5-е **следующего** месяца |

Начислено — агрегат из `salary_accruals` за пересекающиеся `salary_periods`.  
Выплачено — сумма разнесённых банковских строк с типом `payroll` по сотруднику.

Календарь: `App\Support\ManagementPayrollHalfCalendar`.

---

## Импорт выписки

### Формат Сбер «Реестр банковских документов»

Образец: `public/change/АС 09.06.26.xlsx` (если есть в окружении).

| Колонка | Поле |
| --- | --- |
| Дата | `operation_date` |
| Информация | `description` |
| Поступление | приход (`direction=in`) |
| Списание | расход (`direction=out`) |

Строка заголовка: `№ п/п | Дата | Информация | Поступление | Списание`. Данные до строки «Итого».

Парсер: `App\Services\ManagementAccounting\SberRegistryXlsxParser` — XLSX через `ZipArchive` + XML, **без** PhpSpreadsheet.

Сервис импорта: `ManagementAccountingImportService` — дедуп по `line_hash`, автоматический матчинг после вставки.

---

## Матчинг (`ManagementAccountingMatchingService`)

Приоритеты:

1. **Операционный** — номер заявки в тексте: `АС-2606-0001` (regex `АС[-\s]?\d{2}\d{2}[-\s]?\d{4}`), затем строка `payment_schedules` по сумме и дате.
2. **ФОТ** — ФИО сотрудника в назначении платежа → `payroll_paid_sales`.
3. **Статьи по ключевым словам** — `комисс`/`сбор` → `bank_fees`; `ати`/`лиценз`/`подписк` → `services_other`.
4. Иначе — `unclassified` или `cash_other_*` по направлению.

---

## Разнесение (`ManagementAccountingAllocationService`)

Типы подтверждения (`allocation_type`):

| Тип | Действие |
| --- | --- |
| `operational` | Запись оплаты в `payment_schedules` + `PaymentSchedulePaymentLedgerService::recordFromPaymentSchedule()` |
| `payroll` | Увеличение `paid_amount` в `management_payroll_half_users` |
| `category` | Только статья расходов/доходов |

Поддерживаются **частичные** оплаты по одной строке графика (колонки `parent_payment_id`, `is_partial`).

---

## UI и маршруты

| URL | Страница |
| --- | --- |
| `/finance/management-accounting` | `Finance/ManagementAccounting/Index.vue` |
| `/finance/management-accounting/imports/{id}` | `Finance/ManagementAccounting/Reconcile.vue` |

Меню: **Финансы → Управленческий учёт** (`CrmLayout.vue`, ключ `finance-management-accounting`).  
Плитка на `/finance` при `can_access_management_accounting`.

Именованные маршруты: `finance.management-accounting.*`.

---

## Тесты

| Файл | Что проверяет |
| --- | --- |
| `tests/Unit/SberRegistryXlsxParserTest.php` | Парсинг XLSX |
| `tests/Unit/ManagementPayrollHalfCalendarTest.php` | Даты полупериодов 5/20 |
| `tests/Feature/ManagementAccountingAccessTest.php` | Права доступа |

---

## Деплой

```bash
git pull origin master
php artisan migrate --path=database/migrations/2026_06_10_211015_create_management_accounting_tables.php
php artisan db:seed --class=ManagementAccountingSeeder
npm run build
```

На Windows, если `php artisan migrate` падает на загрузке `database/schema/mysql-schema.sql` (`mysql` не в PATH), выполнить миграцию по `--path` на сервере/CI или через прямой вызов `up()` миграции.

Включить галку **«Управленческий учёт»** нужным пользователям в **Настройки → Пользователи**.
