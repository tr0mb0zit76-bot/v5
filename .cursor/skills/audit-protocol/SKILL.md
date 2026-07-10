---
name: audit-protocol
description: >-
  Протокол Аудит CRM v5 — механика + rdudov/agents (05 architecture reviewer, 09 code reviewer)
  + security-review subagent. Активировать при «Протокол Аудит», /audit-protocol, /audit.
---

# Протокол Аудит

Read-only аудит CRM v5 на базе [rdudov/agents](https://github.com/rdudov/agents) + Cursor `security-review`.

**Не править код** в том же прогоне без явной просьбы.

## Промпты rdudov (что используем)

| rdudov | Файл в репо | Зачем |
| --- | --- | --- |
| Architecture reviewer | `agents/rdudov/05_architecture_reviewer_prompt.md` | Архитектура + **§7 Безопасность** |
| Code reviewer | `agents/rdudov/09_agent_code_reviewer.md` | Качество, тесты, совместимость |
| Orchestrator (ref) | `agents/rdudov/01_orchestrator.md` | BLOCKING / MAJOR / MINOR → P0–P3 |

Промпты 02–08 (аналитик … разработчик) — **не** для аудита, только dev pipeline.

Обновить vendor-копии: `pwsh -File scripts/sync-rdudov-agents.ps1`

## Варианты запуска

| Фраза | Действие |
| --- | --- |
| **Протокол Аудит** (default) | Механика + 3× explore (rdudov) + **security-review** + синтез |
| «только механика» | `scripts/audit-protocol.ps1` |
| «с тестами» | ps1 `-RunTests` |
| «без security-review» | 3 explore, без subagent |
| «аудит diff» / «только изменения» | **security-review** + **bugbot** на diff; explore по затронутым файлам |

## Шаг 1 — контекст

1. `docs/sync/Cursor-handoff-latest.md`
2. `AGENTS.md` → домен
3. `docs/sync/v5-local-Components-Code-Audit-2026-07.md`
4. `agents/audit/00_orchestrator.md`
5. `agents/rdudov/README.md`

## Шаг 2 — механика

```powershell
pwsh -File scripts/audit-protocol.ps1
# + тесты: -RunTests
```

## Шаг 3 — субагенты (параллельно, один message)

### A–C: explore + rdudov (обязательно)

| # | Файлы промпта | subagent |
| --- | --- | --- |
| A | `agents/audit/01_architecture_auditor.md` + **`agents/rdudov/05_architecture_reviewer_prompt.md`** | explore, readonly, very thorough |
| B | `agents/audit/03_quality_auditor.md` + **`agents/rdudov/09_agent_code_reviewer.md`** | explore, readonly, medium |
| C | `agents/audit/02_security_auditor.md` + **rdudov 05 §7** + `.cursor/skills/laravel-best-practices/rules/security.md` | explore, readonly, very thorough |

Prompt body (шаблон):

```text
Read-only audit CRM v5.

{agents/audit/0N_*.md — полностью}

Базовый промпт rdudov — прочитай и следуй:
{agents/rdudov/05_... или 09_... — полный файл}

Repository: <abs path>
Mechanical report: <*-mechanical.md>
AGENTS.md + docs/sync/v5-local-Components-Code-Audit-2026-07.md

Классификация rdudov BLOCKING→P0, MAJOR→P1-P2, MINOR→P3.
Не изменяй файлы.
```

### D: security-review (обязательно в полном протоколе)

По skill `review-security` — **один** subagent:

```text
Full Repository Path: <abs path>
Diff: branch changes
Custom Instructions: Read-only security audit CRM v5 (Laravel). IDOR/OrderViewAuthorization, RBAC, MCP Sanctum, v-html XSS, SQL injection, secrets. Mechanical report: <path>. Audit card: docs/sync/v5-local-Components-Code-Audit-2026-07.md. Findings table: Severity, Location, Finding.
```

Если diff пуст (master clean): `Diff: natural language` + `Change Description: Full codebase security audit per AGENTS.md and audit card.`

### E: bugbot (только «аудит diff»)

По skill `review-bugbot`, `Diff: uncommitted changes` или `branch changes`.

## Шаг 4 — синтез

1. mechanical + A + B + C + D (+ E)
2. Дедуп (источник: rdudov-05, rdudov-09, crm, security-review, mechanical)
3. `docs/audit-reports/{timestamp}-audit-report.md`
4. Формат: `agents/audit/00_orchestrator.md`

## Шаг 5 — ответ пользователю

Резюме, топ-5 рисков, путь к отчёту, следующие шаги (с audit card).

## Структура файлов

```
agents/rdudov/          # vendor rdudov/agents (05, 09, …)
agents/audit/           # адаптеры CRM + orchestrator
scripts/audit-protocol.ps1
scripts/sync-rdudov-agents.ps1
.cursor/skills/audit-protocol/SKILL.md
docs/audit-reports/     # gitignore
```
