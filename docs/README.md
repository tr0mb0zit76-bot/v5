# Документация CRM v5

Каталог markdown-файлов репозитория. Пользовательские инструкции и регламенты публикуются в **Книгу продаж** скриптами `scripts/mcp-prod-upsert-*.php`.

## Руководство по CRM (Книга продаж)

| Файл | Статья в Книге | Скрипт |
| --- | --- | --- |
| [order-wizard-user-guide.md](./order-wizard-user-guide.md) | Мастер заказов | `mcp-prod-upsert-order-wizard.php` (план) |
| [order-wizard-financial-terms-user-guide.md](./order-wizard-financial-terms-user-guide.md) | Финансовые условия в мастере заказов | `mcp-prod-upsert-order-wizard.php` |
| [documents-user-guide.md](./documents-user-guide.md) | Документы | `mcp-prod-upsert-documents.php` |
| [ai-assistants-user-guide.md](./ai-assistants-user-guide.md) | Ассистенты CRM | `mcp-prod-upsert-assistants.php` |
| [lead-user-guide.md](./lead-user-guide.md) | Краткая инструкция по лидам | — |
| [leads-mechanism.md](./leads-mechanism.md) | Механизм работы лидов (статусы, БП, nudges) | — |
| [kanban-user-guide.md](./kanban-user-guide.md) | — | — |
| [tasks-user-guide.md](./tasks-user-guide.md) | — | — |
| [disposition-user-guide.md](./disposition-user-guide.md) | — | — |
| [driver-user-guide.md](./driver-user-guide.md) | — | — |
| [vehicle-user-guide.md](./vehicle-user-guide.md) | — | — |

## Регламенты работы (Книга продаж)

| Файл | Статья в Книге | Скрипт |
| --- | --- | --- |
| [documents-regulation.md](./documents-regulation.md) | Регламент работы с документами | `mcp-prod-upsert-documents.php` |
| [order-application-basic-terms-regulation.md](./order-application-basic-terms-regulation.md) | Регламент оформления заявки и изменения базовых условий | `mcp-prod-upsert-assistants.php` |

## AI и интеграции

| Файл | Назначение |
| --- | --- |
| [ai-assistants-user-guide.md](./ai-assistants-user-guide.md) | Command bar, персоны, вложения, память диалога |
| [ai-agent-personas.md](./ai-agent-personas.md) | Slug персон, API (для разработчиков) |
| [ai-platform-architecture.md](./ai-platform-architecture.md) | Уровни AI, аудит, gate |
| [mcp-crm-instructions.md](./mcp-crm-instructions.md) | Список MCP tools (синхрон с `CrmServer`), в т.ч. управленческий учёт |
| [commercial-intelligence-roadmap.md](./commercial-intelligence-roadmap.md) | Коммерческая аналитика, Книга, gap |
| [commercial-roadmap-implementation-tz.md](./commercial-roadmap-implementation-tz.md) | **Сводка шагов 1–5** (портрет, почта, insights, HTML КП, аналитика скриптов) |
| [tz-step-01-portrait-mvp.md](./tz-step-01-portrait-mvp.md) … [tz-step-05](./tz-step-05-scripts-analytics.md) | Детальное ТЗ по каждому шагу |
| [roadmap-2026.md](./roadmap-2026.md) | Общий roadmap 2026 |

## Финансы и график оплат

| Файл | Назначение |
| --- | --- |
| [payment-schedule-architecture.md](./payment-schedule-architecture.md) | Единая модель траншей, расчёт `planned_date`, `payment_schedules`, UI и тесты |
| [management-accounting-architecture.md](./management-accounting-architecture.md) | Управленческий учёт: выписки, разнесение, ФОТ, операционный vs управленческий контур |
| [management-accounting-implementation-plan.md](./management-accounting-implementation-plan.md) | План фаз M0–M5 модуля «Управленческий учёт» |
| [management-accounting-budgeting-integration.md](./management-accounting-budgeting-integration.md) | Связь с бюджетированием: план vs факт, дочерний план продавцов |
| [order-wizard-financial-terms-user-guide.md](./order-wizard-financial-terms-user-guide.md) | Пользовательская инструкция: вкладка «Финансы» в мастере заказа |

## Документы, печать, OCR

| Файл | Назначение |
| --- | --- |
| [print-form-pdf-protection.md](./print-form-pdf-protection.md) | QR-проверка, размеры, party на странице verify, DocMDP (эксперимент) |
| [order-intake-ocr-service.md](./order-intake-ocr-service.md) | Sidecar OCR (локальная интеграция) |
| [order-intake-ocr-production.md](./order-intake-ocr-production.md) | OCR на проде |
| [nextcloud-install.md](./nextcloud-install.md) | WebDAV-хранилище |
| [notifications-departments-ntfy.md](./notifications-departments-ntfy.md) | ntfy, маршрутизация уведомлений |

## Модули (утилиты)

| Файл | Назначение |
| --- | --- |
| [import-cost-calculator-architecture.md](./import-cost-calculator-architecture.md) | Калькулятор растаможки: ЕЭК OData, ПП № 1291, маршруты, деплой, sync справочников |

## Коммерческий контур (2026-06)

| Файл | Назначение |
| --- | --- |
| [commercial-roadmap-implementation-tz.md](./commercial-roadmap-implementation-tz.md) | Порядок шагов 1–5, DoD, checklist для ноутбука |
| [tz-step-01-portrait-mvp.md](./tz-step-01-portrait-mvp.md) | Портрет из лида |
| [tz-step-02-mail-agent.md](./tz-step-02-mail-agent.md) | Агент «Почта» |
| [tz-step-03-insight-drafts.md](./tz-step-03-insight-drafts.md) | HITL insight drafts |
| [tz-step-04-html-proposal-builder.md](./tz-step-04-html-proposal-builder.md) | HTML-конструктор КП |
| [tz-step-05-scripts-analytics.md](./tz-step-05-scripts-analytics.md) | Аналитика скриптов |
| [sync/v5-local-Components-Commercial-Roadmap.md](./sync/v5-local-Components-Commercial-Roadmap.md) | Карта кода, маршруты, тесты (Obsidian) |
| [sync/Cursor-handoff-latest.md](./sync/Cursor-handoff-latest.md) | Handoff на второй ПК |
| [sync/v5-local-Components-Documents-Registry.md](./sync/v5-local-Components-Documents-Registry.md) | Реестр документов, track received, ag-Grid scroll |

## Прочее (внутреннее / MVP)

| Файл | Назначение |
| --- | --- |
| [contractor-portrait-mvp.md](./contractor-portrait-mvp.md) | Портрет контрагента |
| [commercial-intelligence-phase-0-1.md](./commercial-intelligence-phase-0-1.md) | Фазы CI |
| [own-fleet-sprint.md](./own-fleet-sprint.md) | Свой транспорт |
| [scripts-module-implementation-plan.md](./scripts-module-implementation-plan.md) | Модуль скриптов (план фаз) |
| [sales-scripts-editor-guide.md](./sales-scripts-editor-guide.md) | Редактор графа: теги, шаблоны, поля `{code}`, Play |
| [mysql_prepared_statement_error_1615.md](./mysql_prepared_statement_error_1615.md) | MySQL 1615 |

## Публикация в Книгу продаж

```bash
# Документы + регламент документов
php scripts/mcp-prod-upsert-documents.php

# Ассистенты + регламент заявки/базовых условий
php scripts/mcp-prod-upsert-assistants.php

# Финансовые условия в мастере заказов
php scripts/mcp-prod-upsert-order-wizard.php

# Одна статья по подстроке заголовка
MCP_UPSERT_ONLY=Ассистенты php scripts/mcp-prod-upsert-assistants.php
MCP_UPSERT_ONLY=Финансовые php scripts/mcp-prod-upsert-order-wizard.php
```

Требуется `v5-crm-prod` в `~/.cursor/mcp.json` с Bearer-токеном (шаблон: `.cursor/mcp.json.example`). Obsidian vault / Hive Mind: `pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1`. Новые статьи создаются **черновиком** — опубликовать в UI Книги.

## Obsidian / второй компьютер (Yandex Disk)

Vault: `YandexDisk/Exchange/CRM/` — **не в git**, синхронизируется Я.Диском. Канонические копии индексов — в git: [`docs/sync/`](./sync/README.md).  
**Старт сессии Cursor:** [`docs/sync/cursor-agent-startup.md`](./sync/cursor-agent-startup.md) · правило `.cursor/rules/project-context-handoff.mdc`  
**Одно слово между ПК:** **ОТДАТЬ** (поделиться контекстом) · **ЗАБРАТЬ** (подтянуть на другом ПК)

```powershell
# После git pull или правок docs/sync/
pwsh -File scripts/sync-docs-to-yandex.ps1
# если vault в профиле пользователя:
pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "$env:USERPROFILE\Yandex.Disk\Exchange"
pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1   # Obsidian MCP bearer
```

| Файл в git (`docs/sync/`) | На Я.Диске |
| --- | --- |
| `Cursor-handoff-latest.md` | `CRM/Cursor-handoff-latest.md` — контекст для Cursor |
| `cursor-agent-startup.md` | `CRM/cursor-agent-startup.md` — инструкция старта сессии |
| `CRM-00-index.md` | `CRM/00-index.md` — навигация vault |
| `v5-local-00-index.md` | `CRM/v5-local/00-index.md` — карта компонентов |
| `v5-local-Components-*.md` | `CRM/v5-local/Components/*.md` (Commercial Roadmap, Documents Registry, Fleet, Import Cost, …) |

`Exchange/for_note/README.md` — MCP, tools, scripts-local между ПК.

*Обновлено: 2026-06-02.*
