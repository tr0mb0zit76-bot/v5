# Калькулятор растаможки (архитектура)

Ориентировочный расчёт **продажной цены** ввоза спецтехники: таможенная стоимость, пошлина, НДС, таможенный сбор, утильсбор (ПП РФ № 1291), доставка. Суммы округляются до **целых рублей**. Не заменяет таможенную декларацию и TKS.

**Последнее обновление:** 2026-06-02 · коммит `530c6be`

---

## Доступ и маршруты

| Элемент | Значение |
| --- | --- |
| URL | `/modules/import-cost` |
| Имена маршрутов | `modules.import-cost.index`, `modules.import-cost.calculate` |
| Область роли | `modules_import_cost` (`RoleAccess`, `Roles/Index.vue`, `CrmLayout.vue`) |
| Middleware | `visibility.area:modules_import_cost` |

Родительский раздел «Модули» (`/modules`) виден при любой из областей: `modules`, `modules_how_much_fits`, `modules_how_much_costs`, `modules_import_cost`.

---

## Поток расчёта

```
UI (ImportCostCalculator.vue)
  → POST /modules/import-cost/calculate
  → CalculateImportCostRequest
  → ImportCostCalculatorService::calculate()
```

**Таможенная стоимость** = инвойс в ₽ + доставка до границы.

**Пошлина** = таможенная стоимость × ставка % (из справочника ТН ВЭД или override).

**НДС** = (таможенная стоимость + пошлина) × ставка %.

**Таможенный сбор** — шкала по `config/import_cost_calculator.customs_processing_fee_tiers`.

**Утильсбор** (если включён и код требует): `UtilizationFeeCatalog::feeForCategory()` → `round(base_fee_rub × coefficient)` по возрасту (полных лет с года выпуска). База 150 000 ₽ по ПП № 1291.

**Итого «ввоз»** = таможенная стоимость + пошлина + НДС + сбор + утильсбор + доставка после границы + прочие расходы.

---

## Справочники

### Источники данных

| Справочник | Источник | Таблица / fallback |
| --- | --- | --- |
| ТН ВЭД, ставки пошлины/НДС | ЕЭК OData (`portal.eaeunion.org`) + kodtnved.ru (дозаполнение) | `import_cost_tn_ved_entries` → `config/import_cost_calculator` |
| Утильсбор (категории, коэффициенты) | ПП РФ № 1291 (сид + sync) | `import_cost_pp1291_categories` → `config/import_cost_pp1291` |
| Мета синхронизаций | — | `import_cost_reference_syncs` |

### Команда синхронизации

```bash
php artisan import-cost:sync-references           # ПП 1291 + ЕЭК + kodtnved.ru
php artisan import-cost:sync-references --eec-only
php artisan import-cost:sync-references --pp1291-only
php artisan import-cost:sync-references --kodtnved-only
```

**Приоритет ставок пошлины:** `eec` > `kodtnved` > `config`. Kodtnved дозаполняет коды с нулевой ставкой или без синхронизации; не перезаписывает непустые ставки ЕЭК.

**Поиск в UI:** `GET /modules/import-cost/tn-ved/search?q=…` — debounced запрос, без загрузки всего каталога в props.

**Cron:** понедельник 03:15 — `routes/console.php`.

Клиент OData: `App\Services\ImportCost\EecODataClient` (`Accept: application/json;odata=verbose`). При сбое сети — статус `partial` / fallback на config и уже загруженные строки БД.

UI показывает дату и статус последней синхронизации через `ImportCostReferenceMeta::forUi()`.

---

## Ключевые файлы

| Область | Путь |
| --- | --- |
| UI | `resources/js/Pages/Modules/ImportCostCalculator.vue` |
| Контроллер | `app/Http/Controllers/ImportCostCalculatorController.php` |
| Расчёт | `app/Services/ImportCostCalculatorService.php` |
| Валидация | `app/Http/Requests/CalculateImportCostRequest.php` |
| ЕЭК sync | `app/Services/ImportCost/EecTnVedSyncService.php`, `EecODataClient.php` |
| kodtnved sync | `app/Services/ImportCost/KodTnVedReferenceSyncService.php`, `KodTnVedPageParser.php` |
| Категории ПП 1291 по префиксу | `app/Support/ImportCostTnVedCategoryResolver.php` |
| ПП 1291 sync | `app/Services/ImportCost/Pp1291ReferenceSyncService.php` |
| Каталоги | `ImportCostTnVedCatalog`, `UtilizationFeeCatalog`, `ImportCostReferenceMeta` |
| Конфиг | `config/import_cost_calculator.php`, `config/import_cost_pp1291.php` |
| Artisan | `app/Console/Commands/SyncImportCostReferencesCommand.php` |
| Миграции | `2026_06_17_154201..154203` |
| Тесты | `tests/Unit/ImportCostCalculatorServiceTest.php`, `tests/Feature/ImportCostCalculatorTest.php` |

Префиксы кодов ТН ВЭД для OData-выборки: `8429`, `8430`, `8701`, `8704`, `8709` (`config/import_cost_calculator.eec.code_prefixes`).

---

## Деплой на прод

```bash
git pull
php artisan migrate
php artisan import-cost:sync-references
npm run build
php artisan optimize:clear
```

Включить область **`modules_import_cost`** в нужных ролях (Настройки → Роли).

---

## Связь с TKS

Коммерческий TKS.RU API и Python `tks-api` **не используются**. Выбран путь: публичный OData ЕЭК + собственные таблицы ПП № 1291 — достаточно для ориентира продажной цены по спецтехнике.
