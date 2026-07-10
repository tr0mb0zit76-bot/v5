# Протокол Аудит — промпты

Адаптация [rdudov/agents](https://github.com/rdudov/agents) + Cursor subagents.

## Промпты rdudov (vendor)

| Файл | Роль |
| --- | --- |
| `agents/rdudov/05_architecture_reviewer_prompt.md` | Архитектура + **§7 Безопасность** |
| `agents/rdudov/09_agent_code_reviewer.md` | Ревью кода, тесты, регрессии |
| `agents/rdudov/01_orchestrator.md` | Референс (BLOCKING/MAJOR/MINOR) |

Обновление: `pwsh -File scripts/sync-rdudov-agents.ps1`

## Адаптеры CRM + Cursor

| Файл | rdudov | + Cursor |
| --- | --- | --- |
| `01_architecture_auditor.md` | **05** целиком | explore |
| `02_security_auditor.md` | **05 §7** | explore + **security-review** |
| `03_quality_auditor.md` | **09** целиком | explore |

Промпты 02–08 rdudov (аналитик, архитектор, планировщик, разработчик) — **не** входят в аудит; это пайплайн **разработки**.

## Запуск

```
Протокол Аудит
```

Skill: `.cursor/skills/audit-protocol/SKILL.md`

```powershell
pwsh -File scripts/audit-protocol.ps1
pwsh -File scripts/audit-protocol.ps1 -RunTests
```

Отчёты: `docs/audit-reports/`
