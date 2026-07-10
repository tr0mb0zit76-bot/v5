# Протокол Архитектура

Оценка **изящности**, границ модулей, зависимостей, автономности tooling.

| Файл | Роль |
| --- | --- |
| `00_orchestrator.md` | Оркестратор, шкала A–D, формат отчёта |
| `01_elegance_assessor.md` | rdudov **04** + **05** — «на отлично» |
| `02_module_topology.md` | Дубли модулей, границы доменов |
| `03_autonomy_dependencies.md` | Scripts/skills без внешних зависимостей |

## Запуск

```
Протокол Архитектура
```

Skill: `.cursor/skills/architecture-protocol/SKILL.md`

```powershell
pwsh -File scripts/architecture-protocol.ps1   # offline
```

Отчёты: `docs/architecture-reports/`

## vs Протокол Аудит

| | Архитектура | Аудит |
| --- | --- | --- |
| Вопрос | «Красиво и логично ли устроено?» | «Где дыры и баги?» |
| rdudov | 04, 05 | 05, 09, security-review |
