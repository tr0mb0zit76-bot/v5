---
id: component-import-cost-calculator
type: component
status: canon
name: "Import Cost Calculator"
componentType: service
tags: [modules, customs, import, tn-ved]
---

# Import Cost Calculator (Растаможка)

Ориентировочный калькулятор **продажной цены** ввоза спецтехники (без TKS).

## Доступ

- URL: `/modules/import-cost`
- Роль: `modules_import_cost`
- Меню: Модули → Растаможка

## Backend

- `ImportCostCalculatorController`, `ImportCostCalculatorService`
- `CalculateImportCostRequest`
- Sync: `import-cost:sync-references` → `EecTnVedSyncService`, `Pp1291ReferenceSyncService`
- Каталоги: `ImportCostTnVedCatalog`, `UtilizationFeeCatalog`, `ImportCostReferenceMeta`
- Таблицы: `import_cost_tn_ved_entries`, `import_cost_pp1291_categories`, `import_cost_reference_syncs`

## Frontend

- `Modules/ImportCostCalculator.vue`

## Конфиг

- `config/import_cost_calculator.php` — OData ЕЭК, шкала таможенного сбора, fallback ТН ВЭД
- `config/import_cost_pp1291.php` — категории утильсбора, коэффициенты по возрасту

## Документация в git

`docs/import-cost-calculator-architecture.md`

*Обновлено: 2026-06-02.*
