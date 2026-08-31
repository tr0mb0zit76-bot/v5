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
| Сколько стоит (ставка по нормам парка) | `/modules/how-much-costs` | `modules_how_much_costs` |
| **HTML-шаблоны КП** | `/modules/proposal-templates` | `modules_proposal_templates` |
| Считалка | `/modules/counter` | `modules` / sales assistant |
| **Растаможка** | `/modules/import-cost` | `modules_import_cost` |

## Backend

- `LoadingPlannerController`, `ModuleManager`
- `SalesMarginCounterService` — считалка
- `HowMuchCostsCalculatorService` + `OwnFleetCostNormsService` — ставка по нормам (см. [[Fleet and Own Fleet]])
- `ImportCostCalculatorService` — растаможка (см. [[Import Cost Calculator]])

## Frontend

- `Modules/HowMuchFits.vue`, `HowMuchCosts.vue`, `Counter.vue`, `ImportCostCalculator.vue`
- `ProposalTemplates/Index.vue`, `Editor.vue` + `Components/ProposalTemplates/ProposalGrapesEditor.vue` (GrapesJS)

См. также [[Commercial Roadmap]] — шаг 4 (HTML КП).

*Обновлено: 2026-06-22.*
