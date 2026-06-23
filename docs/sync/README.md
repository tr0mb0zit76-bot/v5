# Синхронизация документации с Yandex Disk (второй ПК)

Канонические копии индексов и handoff лежат **в git** (`docs/sync/`). Vault Obsidian на Я.Диске **не в git** — обновляется скриптом или вручную.

## Куда копируется

| Файл в репозитории | Путь на Я.Диске |
| --- | --- |
| `CRM-00-index.md` | `Yandex.Disk/Exchange/CRM/00-index.md` |
| `Cursor-handoff-latest.md` | `Yandex.Disk/Exchange/CRM/Cursor-handoff-latest.md` |
| `cursor-agent-startup.md` | `Yandex.Disk/Exchange/CRM/cursor-agent-startup.md` |
| `v5-local-00-index.md` | `Yandex.Disk/Exchange/CRM/v5-local/00-index.md` |
| `v5-local-Components-Import-Cost-Calculator.md` | `.../v5-local/Components/Import Cost Calculator.md` |
| `v5-local-Components-Management-Accounting.md` | `.../v5-local/Components/Management Accounting.md` |
| `v5-local-Components-Print-Forms-Verification.md` | `.../v5-local/Components/Print Forms Verification.md` |
| `v5-local-Components-Utility-Modules.md` | `.../v5-local/Components/Utility Modules.md` |
| `v5-local-Components-Commercial-Roadmap.md` | `.../v5-local/Components/Commercial Roadmap.md` |
| `v5-local-Components-Fleet-Own-Fleet.md` | `.../v5-local/Components/Fleet Own Fleet.md` |
| `knowledge-graph-notes.md` | `Yandex.Disk/Exchange/CRM/knowledge-graph-notes.md` |

Путь по умолчанию: `C:\Sync\Yandex.Disk\Exchange`. На другом ПК — `-ExchangeRoot`.

## Команды

**С ноута / после правок в git:**

```powershell
pwsh -File scripts/sync-docs-to-yandex.ps1
```

**На «большом» компьютере после `git pull`:**

```powershell
pwsh -File scripts/sync-docs-to-yandex.ps1
pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1   # Obsidian MCP bearer
```

Открыть в Cursor: `@Exchange/CRM/Cursor-handoff-latest.md` или `docs/sync/cursor-agent-startup.md` в репозитории.

Правило агента (в git, после `git pull` на обоих ПК): `.cursor/rules/project-context-handoff.mdc`.

## Что ещё не через git

| Артефакт | Где |
| --- | --- |
| Prod Sanctum MCP | `~/.cursor/mcp.json` → `v5-crm-prod` |
| Hive Mind tools | `Yandex.Disk/Exchange/for_note/tools/` → `<проект>/tools/` |
| Локальные PHP-скрипты | `for_note/scripts-local/` → `scripts/` |
| `.env` | свой на каждой машине |

См. `Exchange/for_note/README.md` на Я.Диске.

*Обновлено: 2026-06-23.*
