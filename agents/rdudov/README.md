# Промпты rdudov/agents (vendor)

Источник: [rdudov/agents](https://github.com/rdudov/agents) · [статья на Habr](https://habr.com/ru/articles/971620/)  
Лицензия: см. `LICENSE` в этой папке.

## Обновление

```powershell
pwsh -File scripts/sync-rdudov-agents.ps1
```

## Какие файлы использует «Протокол Аудит»

| Файл rdudov | Роль в аудите |
| --- | --- |
| `04_architect_prompt.md` | **Протокол Архитектура** — этalon «как должно быть» |
| `05_architecture_reviewer_prompt.md` | Архитектура + §7 Безопасность (**Аудит** + **Архитектура**) |
| `09_agent_code_reviewer.md` | Качество кода, тесты (**Аудит**) |
| `01_orchestrator.md` | Референс формата review (BLOCKING/MAJOR/MINOR) |
| `00_agent_development.md` | Контекст мультиагентного подхода |

Остальные промпты (02 аналитик … 08 разработчик) — **пайплайн разработки**, не read-only аудит.  
Для diff-ревью в Cursor дополнительно: subagent `security-review`, skill `review-security`.

## Адаптеры CRM v5

Тонкая прослойка «режим аудита» + домен CRM:

- `agents/audit/01..03` → rdudov **05**, **09**
- `agents/architecture/01` → rdudov **04**, **05**
