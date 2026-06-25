---
id: component-fleet-own-fleet
type: component
status: canon
name: "Fleet and Own Fleet"
componentType: service
tags: [fleet, own_fleet, fleet_trips, contractors]
---

# Fleet — собственный парк и рейсы

Виртуальный перевозчик **«Собственный парк»** (контрагент id=15 на проде) — **не** «своя компания» в заказе (`is_own_company = false`, не в списке own company).

## UI

| Раздел | URL | Область роли |
| --- | --- | --- |
| Рейсы | `/fleet/trips` | `fleet_trips` / `own_fleet` |
| Эффективность | `/fleet/efficiency` | `fleet_efficiency` |
| ТС / водители | `/fleet/vehicles`, `/fleet/drivers` | `own_fleet`, `drivers` |

Меню: `CrmLayout.vue`, области — `RoleAccess.php` (`own_fleet`, подмодули `fleet_trips`, `fleet_efficiency`).

## Рейс создаётся только при `execution_mode = own_fleet`

**Не путать:** выбор контрагента «Собственный парк» в обычном поиске ≠ свой парк для рейса.

| Действие в мастере заказа | `execution_mode` | Рейс в `fleet_trips` |
| --- | --- | --- |
| Верхняя кнопка «Собственный парк» (голубая) | `own_fleet` | ✅ создаётся при сохранении |
| Тот же контрагент из списка поиска (подпись «Без ИНН») | `null` | ❌ не создаётся |

С **2026-06-23** (`078b41d`): дубль убран из поиска; выбор виртуального парка из списка тоже ставит `own_fleet`.

Синхронизация: `FleetTripService::syncPlannedTripsFromOrder` ← `OrderWizardService` после сохранения `financial_terms`.  
Удаления рейсов при смене на внешнего перевозчика **пока нет** — возможны «зависшие» рейсы (см. runbook ниже).

## Код

| Слой | Файлы |
| --- | --- |
| Каталог | `app/Support/OwnFleetCatalog.php`, `resources/js/support/ownFleetCatalog.js` |
| Контрагент | `app/Services/OwnFleetContractorService.php` |
| Рейсы | `app/Services/FleetTripService.php`, `app/Models/FleetTrip.php`, `FleetTripController.php` |
| Мастер | `resources/js/Pages/Orders/Wizard.vue` (`selectOwnFleetPerformer`, payload `performers`) |
| Исключение из own company | `Contractor::ownCompanyProfiles()`, `ContractorController` |

## Данные на заказе

- `orders.performers` — JSON с `execution_mode`, `fleet_vehicle_id`, `fleet_driver_id`, `fleet_trip_id` (часто `null` на старых заказах).
- `financial_terms.contractors_costs` — зеркало `execution_mode` через `syncContractorsCostsWithPerformers`.
- `order_legs.metadata.performer` — фактические даты, ТС/водитель.

## Дедупликация ТС (2026-06-02)

`App\Services\Fleet\FleetVehicleRegistry` — при создании (UI `/fleet/vehicles`, MCP, портал перевозчика) ищет запись по **`owner_contractor_id` + нормализованный госномер** (тягач, затем прицеп). Повтор → обновление существующей карточки, не новая строка.

Редактирование: **Авто** → двойной клик → `VehicleWizard` (PATCH). Область роли: **`drivers`**. Из мастера заказа — только выбор `fleet_vehicle_id`.

## Печать: ТС и водитель в заказе

Снимок: `OrderPrintFormDraftService` → `resolvePrimaryFleetSelection` из `performers` / `order_legs.metadata.performer`.  
Плейсхолдеры: `vehicle.number`, `driver.full_name`; legacy `gosnomer`, `gosnomer_TS` (с 2026-06-02).

## Runbook (прод)

**Лишний рейс** (перевозчик уже внешний):

```sql
DELETE FROM fleet_trips WHERE order_id = (SELECT id FROM orders WHERE order_number = '…' LIMIT 1);
```

**Рейс не создался** (Собственный парк без `own_fleet`):

1. Предпочтительно: открыть заказ → верхняя кнопка «Собственный парк» → ТС/водитель → сохранить.
2. Если мастер не сохраняет (рассинхрон `company_code` / номера): вручную в БД — `execution_mode=own_fleet` в `contractors_costs`, строка в `fleet_trips` (см. handoff 2026-06-23, СП-2606-0003).

**Дубликат номеров СП/AS:** `company_code` берётся от «своей компании» в мастере; номер может прийти из шаблона/формы с другим префиксом — уникального индекса на `order_number` нет.

## Тесты

`tests/Unit/VirtualOwnFleetContractorTest.php`  
PHPUnit: `.env.testing` → `DB_DATABASE=u_tromb_test` (не рабочая `u_tromb`).

*Обновлено: 2026-06-02.*
