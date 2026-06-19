# Условия оплаты и график оплат (архитектура)

Техническая карта единой модели траншей (2026-06).

**Пользовательская инструкция:** [`order-wizard-financial-terms-user-guide.md`](./order-wizard-financial-terms-user-guide.md) (Книга продаж → Руководство по CRM). Кратко — раздел 7 в [`order-wizard-user-guide.md`](./order-wizard-user-guide.md).

**Последнее обновление:** 2026-06-13 (наличка → срок от выгрузки, частичные оплаты в гриде)

---

## Модель данных

### JSON в заказе / контрагенте

График хранится как объект с массивом `installments` (до **10** траншей на сторону):

```json
{
  "installments": [
    {
      "percent": 50,
      "amount": 125000,
      "offset_days": 0,
      "offset_unit": "calendar_days",
      "anchor": "last_unloading",
      "basis": "unloading"
    }
  ]
}
```

| Поле | Назначение |
| --- | --- |
| `percent` / `amount` | Доля и сумма транша (синхронизируются с ценой стороны) |
| `offset_days` | Сдвиг относительно якоря (−730…+730) |
| `offset_unit` | `calendar_days` или `bank_days` |
| `anchor` | Точка отсчёта: `first_loading`, `last_unloading`, `border_crossing`, `order_date`, `loading_date`, `unloading_date` |
| `basis` | Событие для расчёта даты: `fttn`, `fttn_receipt`, `ottn`, `loading`, `unloading` |

**Легаси** (`has_prepayment`, `prepayment_*`, `postpayment_*`) при чтении конвертируется в `installments`, ключи удаляются.

Где лежит:

- заказчик: `financial_terms.client_payment_schedule`, текст — `client_payment_terms`;
- перевозчик / плечо: `contractors_costs[].payment_schedule`, текст — `payment_terms`;
- дефолты контрагента: `default_customer_payment_schedule`, `default_carrier_payment_schedule`.

### Таблица `payment_schedules`

Строки графика **суммируются по сторонам**: заказчик (N траншей) + каждый перевозчик/подрядчик (M траншей) = N+M строк в гриде.

| Колонка | Смысл |
| --- | --- |
| `party` | `customer` / `carrier` / `contractor` |
| `type` | `prepayment` / `final` (ровно 2 транша) или `installment` (1 или 3+) |
| `installment_sequence` | Порядковый номер транша (1-based), ключ для сохранения фактических оплат при пересборке |
| `planned_date` | Плановая дата (ISO `Y-m-d` в БД) |
| `actual_date` | Факт оплаты |
| `amount`, `status`, … | Сумма, статус, счёт, частичные оплаты |

Миграция: `database/migrations/2026_06_08_155321_add_installment_sequence_to_payment_schedules_table.php`.

---

## Расчёт `planned_date`

Цепочка в `OrderCompensationService::plannedDateForInstallmentRow()`:

1. **События документов / погрузки / выгрузки** (`basis` = `fttn`, `fttn_receipt`, `ottn`, `loading`, `unloading`):
   - дата события через `resolveScheduleDate()` (сканы, квиток в гриде документов, факт/план точек маршрута);
   - **наличная форма оплаты** (`payment_form` = `cash` у стороны заказа): базисы `fttn`, `fttn_receipt`, `ottn` нормализуются в **`unloading`** (`PaymentScheduleCashBasis::effectiveBasisForParty`) — срок считается от фактической выгрузки, без ожидания УПД;
   - сдвиг `offset_days` + `offset_unit` (`CalendarBankDayShifter`).
2. **Якорь + сдвиг** (прочие случаи): `PaymentInstallmentPlanner::plannedDateForInstallment()` по `anchor` и контексту дат заказа.

### Источник дат погрузки / выгрузки

`OrderRouteMilestoneDateResolver` — единый приоритет:

**факт `route_points.actual_date` → план `planned_date` → performers → колонки заказа `loading_date` / `unloading_date`.**

Синхронизация колонок заказа:

- при сохранении мастера — `OrderWizardService`;
- при установке факта на точке — `OrderRouteActualDateUpdateService`.

### Две «даты документов»

| Что | Где задаётся |
| --- | --- |
| FTTN (по сканам) | Автоматически: `OrderDocumentRequirementService::paymentPackageAttachedAt()` по прикреплённым файлам |
| Квиток / OTTN | Вручную в гриде документов: `track_received_date_customer` / `track_received_date_carrier` |

Стороны считаются **раздельно** — квиток перевозчика не подставляется на заказчика.

---

## Пересборка графика

Триггер: сохранение заказа → `OrderCompensationService::syncPaymentSchedules()`.

1. Снимок фактических оплат: `PaymentScheduleSettlementPreserver::snapshot()` (ключ: `party` + `counterparty_id` + `installment_sequence`, fallback на `type`).
2. Удаление старых строк (chunk, обход MySQL 1615).
3. Построение новых строк из `installments` каждой стороны.
4. Восстановление оплат из снимка.

Нормализация входа:

- PHP: `PaymentInstallmentScheduleNormalizer`, `PaymentScheduleLegacyConverter`;
- JS: `resources/js/support/orderPaymentScheduleUi.js` (`applyInstallmentScheduleInPlace`, `MAX_INSTALLMENTS = 10`).

---

## UI

| Место | Компонент / файл |
| --- | --- |
| Мастер заказа, контрагент | `PaymentTermsWizardBlock.vue` |
| Общий API траншей | `orderPaymentScheduleUi.js` |
| Грид «График оплат» | `CashFlowGrid.vue` — даты **дд.мм.гггг** (`formatGridDate`); колонка **«К оплате»**, статус «Частично оплачено» (`cashFlowJournalStats.js`, `FinanceOverviewService`) |
| Страница финансов | `Pages/Finance/Index.vue` |

Режимы блока условий: **«Один транш»** / **«Несколько траншей»** + «Добавить транш». Сводка для договора — авто из траншей, можно править вручную (`editable-summary`).

**Важно для фронта:** не вешать `watch({ deep: true })` на весь `schedule` с мутацией внутри — цикл пересчёта сумм. Нормализация — один раз при mount / смене ссылки; суммы — при смене `totalAmount`.

---

## Права и маршруты

- Область видимости: `payment_schedules` в `RoleAccess`.
- Грид: `FinanceIndexController`, `CashFlowGrid.vue`.
- Действия по строке: `PaymentScheduleController` (`record-payment`, `cancel`, `restore`, `invoice-number`).

---

## Тесты

| Файл | Что проверяет |
| --- | --- |
| `tests/Unit/PaymentScheduleLegacyConverterTest.php` | legacy → installments |
| `tests/Feature/PaymentScheduleUnloadingDateTest.php` | `planned_date` при `basis=unloading`, синхронизация дат маршрута |
| `tests/Unit/PaymentScheduleCashBasisTest.php` | наличка: `fttn`/`ottn` → `unloading` |

---

## Artisan

| Команда | Назначение |
| --- | --- |
| `payment-schedules:sync-settlement-amounts` | Пересчёт `paid_amount` / остатков и частичных строк по журналу после смены логики разнесения |
| `payment-schedules:backfill-payment-events` | Журнал оплат из исторических `paid_amount` (см. управленческий учёт) |

---

## Деплой

```bash
git pull origin master
php artisan migrate
php artisan payment-schedules:sync-settlement-amounts   # после обновления логики частичных оплат
npm run build
```

После миграции существующие заказы при следующем сохранении пересоберут `payment_schedules` с `installment_sequence`.
