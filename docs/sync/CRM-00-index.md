# CRM — навигация vault

> Vault: `Yandex.Disk/Exchange/CRM` · Код: `C:/OSPanel/home/v5.local` · Синхронизация vault — **Yandex Disk**, не git.  
> Источник в git: `docs/sync/CRM-00-index.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

## Cursor (второй ПК)

- [[Cursor-handoff-latest|Handoff — актуальный контекст для агента]] ← **читать первым**
- [[cursor-agent-startup|Старт сессии Cursor — оба ПК]] ← **инструкция для агента и человека**

## План и история

- [[Roadmap/00-vision|Видение]]
- [[Roadmap/2026-Q2|Roadmap Q2 2026]] ← **текущий квартал**
- [[Roadmap/backlog|Backlog]]
- [[Roadmap/integrations-watchlist|Integrations watchlist]]
- [[Changelog/2026-06|Changelog — июнь 2026]]

## Runbooks (прод)

- [[Runbooks/ntfy VPS deploy|ntfy на VPS]]

## Регламенты

- [[Policies/Регламент работы с документами CRM|Документы в CRM v1.0]] · git: `docs/documents-regulation.md`

## Решения (ADR)

- [[Decisions/ADR Contractor Risk Scoring v2|Scoring v2 + HITL]]
- [[Decisions/ADR Notifications ntfy and departments|ntfy + подразделения]] ← актуально
- [[Decisions/ADR Notifications ntfy and office groups|ntfy + офисы]] _(superseded)_

## Модули и дизайн (backlog)

- [[Roadmap/Modules/OSINT Security Module|OSINT для СБ]]
- [[Roadmap/Design/Activepieces UI Ideas|Activepieces — UI-идеи]]
- [[Roadmap/Design/AI Agent Personas|AI Agent Personas — Джарвис, Галя, Юрик, Страж]]

- [[knowledge-graph-notes|Граф знаний — Obsidian vs Hive Mind]]

## Архитектура (Hive Mind)

- [[v5-local/00-index|Карта компонентов v5-local]]
- [[v5-local/Components/Fleet Own Fleet|Собственный парк и рейсы (2026-06)]]
- [[v5-local/Components/Commercial Roadmap|Коммерческий контур — шаги 1–5 (2026-06)]]
- [[v5-local/Components/Import Cost Calculator|Растаможка — калькулятор ввоза]]
- [[v5-local/Components/Management Accounting|Управленческий учёт — план/факт, split]]
- [[v5-local/Components/Print Forms Verification|Печать — QR и страница verify]]

## Документация в репозитории

| Тема | Путь в git |
|------|------------|
| Уведомления, подразделения, ntfy | `docs/notifications-departments-ntfy.md` |
| ntfy sidecar | `deploy/ntfy/README.md` |
| Roadmap CRM | `docs/roadmap-2026.md` |
| MCP | `docs/mcp-crm-instructions.md` |
| Управленческий учёт | `docs/management-accounting-architecture.md` |
| План vs факт (бюджет) | `docs/management-accounting-budgeting-integration.md` |
| План внедрения управленки | `docs/management-accounting-implementation-plan.md` |
| График оплат | `docs/payment-schedule-architecture.md` |
| **Печать: QR / verify** | `docs/print-form-pdf-protection.md` |
| **Растаможка (архитектура)** | `docs/import-cost-calculator-architecture.md` |
| **Commercial roadmap (ТЗ + handoff)** | `docs/commercial-roadmap-implementation-tz.md`, `docs/sync/v5-local-Components-Commercial-Roadmap.md` |
| **Handoff второй ПК** | `docs/sync/Cursor-handoff-latest.md` |
| **Граф знаний (Obsidian vs код)** | `docs/sync/knowledge-graph-notes.md` |
| **Fleet / рейсы** | `docs/sync/v5-local-Components-Fleet-Own-Fleet.md` |
| AI personas (command bar) | `docs/ai-agent-personas.md` |
| **Документы (пользователи)** | `docs/documents-user-guide.md` |
| **Документы (регламент v1)** | `docs/documents-regulation.md` |
| Мастер заказов | `docs/order-wizard-user-guide.md` |
| Синхрон vault ↔ git | `docs/sync/README.md` |

*Обновлено: 2026-06-23.*
