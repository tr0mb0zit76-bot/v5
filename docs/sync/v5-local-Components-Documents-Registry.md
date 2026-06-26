# Documents — реестр, учёт, дата получения оригиналов

> Источник в git: `docs/sync/v5-local-Components-Documents-Registry.md`  
> Пользовательская инструкция: `docs/documents-user-guide.md` · регламент: `docs/documents-regulation.md`

## Назначение

- **Реестр** (`/documents`) — ag-Grid по заказам, колонки по типам документов + даты «Оригиналы заказчика/перевозчика».
- **Учёт документов** в мастере — таблица обязательных слотов + колонка «Дата получения» в тех же строках (заявка, УПД и т.д.).

## Дата получения (`track_received_date_*`)

| Слой | Файлы |
| --- | --- |
| Нужна ли дата | `OrderTrackReceivedRequirementResolver` — базисы `ottn`, `fttn_receipt` в графике (**в т.ч. наличка**); при `cash` + `fttn` — нет (срок от выгрузки) |
| Права | `RoleAccess::canEditTrackReceivedDates()` — роль **clerk** (делопроизводитель) + admin |
| API реестра | `PATCH documents/orders/{order}/track-received` → `DocumentRegistryController::updateTrackReceived` |
| Inline в мастере | `orders.inline-update` → `OrderInlineFieldUpdateService` |
| Пересборка графика | после сохранения даты — `resyncPaymentSchedulesForOrder` |
| Фронт реестра | `DocumentsGrid.vue` — колонки `track_received_date_customer/carrier`, kind `track-received-date` |
| Фронт учёта | `OrderSignedDocumentsTable.vue` + `attachTrackReceivedToRegistryRows()` в `orderTrackingDates.js` |

**Одна дата на сторону** (заказчик / перевозчик): если график стороны требует track received, дата показывается во **всех** строках слотов `*_request` и `*_closing` (не только заявка при `ottn`).

## ag-Grid — горизонтальный скролл

Общие хелперы (все гриды с нижней панелью):

- `resources/js/support/agGridHorizontalScroll.js` — ширина = сумма колонок; `min-width` только у `.ag-center-cols-container` (не хедер).
- `resources/js/support/useAgGridHorizontalPanel.js` — синхронизация нижнего скролла с center viewport.

## Экспорт Excel

- `gridExcelExport.js`, `GridExportDialog.vue` — заказы и контрагенты; флаг `can_export_grid` в `RoleAccess` / Inertia.

## Тесты

- `tests/Feature/Documents/DocumentRegistryTrackReceivedTest.php`
- `tests/Unit/OrderTrackReceivedRequirementResolverTest.php`
- `tests/Unit/RoleAccessTrackReceivedDatesTest.php`

*Обновлено: 2026-06-02.*
