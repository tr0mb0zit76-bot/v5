---
id: component-utility-modules
type: component
status: canon
name: "Utility Modules"
componentType: service
tags: [modules, calculator]
---

# Utility Modules

Каталог утилит в разделе **Модули**.

| Модуль | URL | Область роли |
| --- | --- | --- |
| Сколько влезет | `/modules/how-much-fits` | `modules_how_much_fits` |
| Сколько стоит (маржа) | `/modules/how-much-costs` | `modules_how_much_costs` |
| Считалка | `/modules/counter` | `modules` / sales assistant |
| **Растаможка** | `/modules/import-cost` | `modules_import_cost` |

## Backend

- `LoadingPlannerController`, `ModuleManager`
- `SalesMarginCounterService` — считалка
- `ImportCostCalculatorService` — растаможка (см. [[Import Cost Calculator]])

## Frontend

- `Modules/HowMuchFits.vue`, `HowMuchCosts.vue`, `Counter.vue`, `ImportCostCalculator.vue`

*Обновлено: 2026-06-02.*
