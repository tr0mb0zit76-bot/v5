---
name: architecture-protocol
description: >-
  Протокол Архитектура CRM v5 — изящность решения, границы модулей, дубли,
  зависимости, автономность scripts/skills. rdudov 04+05. Активировать при
  «Протокол Архитектура», /architecture-protocol, «оцени архитектуру».
---

# Протокол Архитектура

Read-only оценка **общей изящности** и структуры CRM v5. Не заменяет «Протокол Аудит» (security/P0).

## Когда активировать

- **Протокол Архитектура**, `/architecture-protocol`
- «Оцени архитектуру», «нет ли дублирующих модулей», «насколько изящно устроен проект»

## Принцип автономности

- Скрипт `architecture-protocol.ps1` — **offline** (локальный git, php, rg)
- Промпты rdudov — **vendor** в `agents/rdudov/` (не fetch при каждом запуске)
- Обновление vendor вручную: `sync-rdudov-agents.ps1`

## Варианты

| Фраза | Действие |
| --- | --- |
| Default | Механика + 3 explore + синтез + оценка A–D |
| «только механика» | Только ps1 |
| «фокус Orders» | Сузить субагентов к домену |

## Шаг 1 — контекст

1. `AGENTS.md` → домен, эталоны (Load Board vs Wizard)
2. `docs/sync/v5-local-00-index.md`
3. `agents/architecture/00_orchestrator.md`
4. `agents/rdudov/04_architect_prompt.md`, `05_architecture_reviewer_prompt.md`

## Шаг 2 — механика (offline)

```powershell
pwsh -File scripts/architecture-protocol.ps1
```

## Шаг 3 — три субагента (parallel, readonly explore)

| # | Адаптер | rdudov | Thoroughness |
| --- | --- | --- | --- |
| A | `01_elegance_assessor.md` | **04** + **05** | very thorough |
| B | `02_module_topology.md` | — | very thorough |
| C | `03_autonomy_dependencies.md` | — | medium |

Prompt template:

```text
Read-only architecture assessment CRM v5.

{agents/architecture/0N_*.md — полностью}

Базовый этalon rdudov (прочитай):
{04 и/или 05 если указано}

Repository: <abs>
Mechanical: <*-mechanical.md>
AGENTS.md + v5-local-00-index.md

Оценка изящности, дубли модулей, автономность. Не меняй код.
```

## Шаг 4 — синтез

`docs/architecture-reports/{timestamp}-architecture-report.md`

Формат: `agents/architecture/00_orchestrator.md` — **оценка A/B/C/D**, roadmap к A.

## Шаг 5 — ответ пользователю

1. Оценка **A–D** одной строкой
2. Топ-5 structural improvements
3. Что уже «на отлично»
4. Путь к полному отчёту

## Файлы

```
agents/architecture/
agents/rdudov/04_architect_prompt.md
scripts/architecture-protocol.ps1
docs/architecture-reports/   # gitignore
```

## Не делать

- Security deep-dive → «Протокол Аудит»
- Правки кода / commit без запроса
- Сеть при механике
