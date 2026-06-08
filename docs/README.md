# Документация CRM v5

Каталог markdown-файлов репозитория. Пользовательские инструкции и регламенты публикуются в **Книгу продаж** скриптами `scripts/mcp-prod-upsert-*.php`.

## Руководство по CRM (Книга продаж)

| Файл | Статья в Книге | Скрипт |
| --- | --- | --- |
| [order-wizard-user-guide.md](./order-wizard-user-guide.md) | Мастер заказов | — |
| [documents-user-guide.md](./documents-user-guide.md) | Документы | `mcp-prod-upsert-documents.php` |
| [ai-assistants-user-guide.md](./ai-assistants-user-guide.md) | Ассистенты CRM | `mcp-prod-upsert-assistants.php` |
| [lead-user-guide.md](./lead-user-guide.md) | — | — |
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
| [mcp-crm-instructions.md](./mcp-crm-instructions.md) | Список MCP tools (синхрон с `CrmServer`) |
| [commercial-intelligence-roadmap.md](./commercial-intelligence-roadmap.md) | Коммерческая аналитика, Книга, gap |
| [roadmap-2026.md](./roadmap-2026.md) | Общий roadmap 2026 |

## Финансы и график оплат

| Файл | Назначение |
| --- | --- |
| [payment-schedule-architecture.md](./payment-schedule-architecture.md) | Единая модель траншей, расчёт `planned_date`, `payment_schedules`, UI и тесты |

## Документы, печать, OCR

| Файл | Назначение |
| --- | --- |
| [order-intake-ocr-service.md](./order-intake-ocr-service.md) | Sidecar OCR (локальная интеграция) |
| [order-intake-ocr-production.md](./order-intake-ocr-production.md) | OCR на проде |
| [nextcloud-install.md](./nextcloud-install.md) | WebDAV-хранилище |
| [notifications-departments-ntfy.md](./notifications-departments-ntfy.md) | ntfy, маршрутизация уведомлений |

## Прочее (внутреннее / MVP)

| Файл | Назначение |
| --- | --- |
| [contractor-portrait-mvp.md](./contractor-portrait-mvp.md) | Портрет контрагента |
| [commercial-intelligence-phase-0-1.md](./commercial-intelligence-phase-0-1.md) | Фазы CI |
| [own-fleet-sprint.md](./own-fleet-sprint.md) | Свой транспорт |
| [scripts-module-implementation-plan.md](./scripts-module-implementation-plan.md) | Модуль скриптов |
| [mysql_prepared_statement_error_1615.md](./mysql_prepared_statement_error_1615.md) | MySQL 1615 |

## Публикация в Книгу продаж

```bash
# Документы + регламент документов
php scripts/mcp-prod-upsert-documents.php

# Ассистенты + регламент заявки/базовых условий
php scripts/mcp-prod-upsert-assistants.php

# Одна статья по подстроке заголовка
MCP_UPSERT_ONLY=Ассистенты php scripts/mcp-prod-upsert-assistants.php
```

Требуется `v5-crm-prod` в `~/.cursor/mcp.json` с Bearer-токеном (шаблон: `.cursor/mcp.json.example`). Obsidian vault / Hive Mind: `pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1`. Новые статьи создаются **черновиком** — опубликовать в UI Книги.

*Обновлено: 2026-06-08.*
