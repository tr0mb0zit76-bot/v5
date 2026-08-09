# CRM v5-local — карта архитектуры

> Индекс для Obsidian. Сущности Hive Mind: `system`, `component`, `decision`, `interface`, `constraint`.  
> Актуальный контекст сессии: [[../Cursor-handoff-latest]].  
> Источник в git: `docs/sync/v5-local-00-index.md`

## Система

- [[CRM Avtoalyans v5]]

## Домены (Components)

- [[Orders]] · [[Leads]] · [[Contractors]] · [[Sales Assistant]] · [[Sales Book]] · [[Sales Scripts Editor]]
- [[Finance]] · [[Management Accounting]] · [[Fleet]] · [[Mail]] · [[Documents]]
- [[Tasks and Kanban]] · [[Reports]] · [[Roles and Users]] · [[Settings]]
- [[Utility Modules]] · [[Import Cost Calculator]] · [[Commercial Roadmap]] · [[Integrations]] · [[Improvement Loop]]

## Сквозные слои

- [[Role Access and Visibility]] · [[Inertia Frontend]] · [[Print Forms DOCX]] · [[Print Forms Verification]] · [[MCP and Command Bar]]

## Интерфейсы

- [[MCP CRM API]] · [[Carrier Portal]] · [[Public Document Verification]]

## Решения (ADR)

- [[ADR Laravel Inertia SPA]] · [[ADR Sanctum MCP Auth]] · [[ADR DOCX Print Pipeline]]

## Ограничения

- [[Constraint PHP 83 Laravel 13]] · [[Constraint Visibility RBAC]]

Код: `C:/OSPanel/home/v5.local`

## Компоненты с карточками в git

| Компонент | Файл sync |
| --- | --- |
| **Commercial roadmap (1–5)** | `v5-local-Components-Commercial-Roadmap.md` |
| **Обогащение портрета** | `v5-local-Components-Contractor-Enrichment.md` |
| **Коннектор 1С БП** | `v5-local-Components-OneC-BP-Connector.md` |
| **Контур улучшений** | `v5-local-Components-Improvement-Loop.md` |
| **Собственный парк / Рейсы** | `v5-local-Components-Fleet-Own-Fleet.md` |
| **Документы / реестр / track received** | `v5-local-Components-Documents-Registry.md` |
| **Книга продаж** | `v5-local-Components-Sales-Book.md` |
| Растаможка | `v5-local-Components-Import-Cost-Calculator.md` |
| Управленческий учёт | `v5-local-Components-Management-Accounting.md` |
| QR / verify печати | `v5-local-Components-Print-Forms-Verification.md` |
| Утилиты | `v5-local-Components-Utility-Modules.md` |
| **Code audit 2026-07** | `v5-local-Components-Code-Audit-2026-07.md` |

*Обновлено: 2026-07-10.*

## Документация лидов (git, без отдельной component-карточки)

| Файл | Назначение |
| --- | --- |
| `docs/lead-user-guide.md` | Краткая инструкция; источник для Книги продаж id=19 |
| `docs/leads-mechanism.md` | Полный регламент (статусы, БП, nudges, предрасчёт, конвертация) |
| `scripts/sync-leads-sales-book-article.php` | Push краткой инструкции в `sales_book_articles` |
