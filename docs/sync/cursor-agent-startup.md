# Cursor — старт сессии (оба компьютера)

> **Канон в git:** `docs/sync/cursor-agent-startup.md`  
> **Копия на Я.Диске:** `Exchange/CRM/cursor-agent-startup.md`  
> **Синхронизация:** `pwsh -File scripts/sync-docs-to-yandex.ps1`

Инструкция для **человека** и для **агента Cursor**: как не терять контекст между ПК и чатами.

---

## Зачем

- Код и актуальные индексы — в **git** (`docs/sync/`, `AGENTS.md`).
- Obsidian vault на **Я.Диске** — для второго ПК и `@`-упоминаний в Cursor; обновляется **из git**, не наоборот.
- Агент **не должен** начинать задачу только с догадок из старого чата.

---

## Первый ПК (основной, где пушите в git)

### В начале сессии Cursor

1. При необходимости: `git pull`.
2. Агент читает:
   - `docs/sync/Cursor-handoff-latest.md`
   - этот файл
   - `AGENTS.md` (раздел «Домен приложения»)
3. По теме задачи — карточку из `docs/sync/v5-local-Components-*.md`.

Правило в репозитории: `.cursor/rules/project-context-handoff.mdc` (`alwaysApply: true`) — подхватывается Cursor автоматически после `git pull`.

### В конце сессии (если было что-то важное)

1. Обновить `docs/sync/Cursor-handoff-latest.md` (дата, ветка, HEAD, что сделано).
2. При новых модулях — карточку в `docs/sync/` и ссылку в handoff.
3. Синхронизировать vault:
   ```powershell
   pwsh -File scripts/sync-docs-to-yandex.ps1
   ```
   Если vault не в `C:\Sync\Yandex.Disk\Exchange`:
   ```powershell
   pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "$env:USERPROFILE\Yandex.Disk\Exchange"
   ```

---

## Второй ПК (ноутбук, Obsidian)

### Один раз / после клонирования

```powershell
git clone git@github.com:tr0mb0zit76-bot/v5.git C:\OSPanel\home\v5.local
cd C:\OSPanel\home\v5.local
composer install
npm ci
# .env — свой; DB_HOST=127.0.1.21 для OSPanel
# Тесты: copy u_tromb.env.example .env.testing → DB_DATABASE=u_tromb_test (рабочая u_tromb отдельно)
```

Cursor: скопировать MCP из `for_note/cursor-mcp.project.json` или:
```powershell
pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1
```

### Каждая сессия Cursor

1. **Git:**
   ```powershell
   cd C:\OSPanel\home\v5.local
   git pull
   ```
2. **Документация в vault (из git):**
   ```powershell
   pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "$env:USERPROFILE\Yandex.Disk\Exchange"
   ```
   (подставьте свой путь к папке `Exchange`.)
3. **Открыть в Cursor** один из вариантов:
   - файлы в репозитории: `docs/sync/Cursor-handoff-latest.md`
   - или `@`-упоминание vault: `Exchange/CRM/Cursor-handoff-latest.md`
4. Попросить агента: *«Сначала прочитай handoff и AGENTS.md по инструкции cursor-agent-startup»* — правило `.cursor/rules/project-context-handoff.mdc` должно сработать само.

### Чего не делать

- Не считать vault на Я.Диске единственным источником правды — он может **отставать** от git.
- Не копировать `.env` и `~/.cursor/mcp.json` с другого ПК без осознанной настройки.

---

## Локальное окружение (оба ПК одинаково)

Канон в **git** — не копируйте `.env` целиком между машинами.

| Что | Канон | После `git pull` на каждом ПК |
| --- | --- | --- |
| **Vite HMR** | порт **5174**, `strictPort: true` в `vite.config.js` | `npm run dev` → `http://127.0.0.1:5174/`. В `.env` (не в git): `VITE_DEV_SERVER_URL=http://127.0.0.1:5174` — см. `.env.example` |
| **optimizeDeps** | только JS-пакеты; **не** включать `*.css` (иначе Vite падает на pre-bundle) | уже в `vite.config.js` |
| Другой проект на том же ПК | свой порт (часто 5173) | не менять порт CRM на 5173 |
| PHP deps | `composer.lock` | при смене lock: `composer install` |
| JS deps | `package-lock.json` | при смене lock: `npm ci` |
| Миграции | `database/migrations/` | `php artisan migrate` |
| Тесты | `.env.testing` → `u_tromb_test` | не путать с рабочей БД |
| OSPanel DB | `DB_HOST=127.0.1.21` (типично) | свой `.env` |
| MCP | Я.Диск / `sync-cursor-mcp-from-yandex.ps1` | не коммитить токены |

Laravel в dev читает URL из `public/hot` (его пишет Vite). Порт в `vite.config.js` — источник правды; строка в `.env` нужна, чтобы оба ПК не расходились «на глаз».

---

## Карта файлов контекста

| Файл | Назначение |
| --- | --- |
| `docs/sync/Cursor-handoff-latest.md` | Последние изменения, HEAD, прод-чеклист |
| `docs/sync/cursor-agent-startup.md` | Эта инструкция |
| `docs/sync/prod-ssh.md` | SSH на прод: IP `91.229.11.16`, PPK `C:\,ssh\private_key.ppk` |
| `docs/sync/CRM-00-index.md` | Оглавление vault |
| `docs/sync/v5-local-00-index.md` | Карта компонентов кода |
| `docs/sync/v5-local-Components-*.md` | Тематические карточки |
| `docs/sync/knowledge-graph-notes.md` | Obsidian-граф vs Hive Mind (продукт vs код) |
| `docs/sync/v5-local-Components-Fleet-Own-Fleet.md` | Рейсы, own_fleet, runbook |
| `AGENTS.md` | Правила агента + доменная карта |
| `.cursor/rules/project-context-handoff.mdc` | Автоправило Cursor |

---

## ОТДАТЬ / ЗАБРАТЬ (одно слово)

Два триггера для переноса контекста между **основным ПК** и **большим компьютером** (ноутбук / Obsidian). Агент Cursor понимает их без длинной инструкции.

### ОТДАТЬ — «поделиться знаниями»

С конца сессии на ПК, где вы кодили:

1. Обновить `docs/sync/Cursor-handoff-latest.md` (и индексы/карточки, если менялся домен).
2. Закоммитить и запушить в `master` (если пользователь не запретил).
3. Скопировать индексы в vault:
   ```powershell
   pwsh -File scripts/sync-docs-to-yandex.ps1
   ```

### ЗАБРАТЬ — «забрать знания»

В начале сессии на **другом** ПК:

1. ```powershell
   cd C:\OSPanel\home\v5.local
   git pull
   pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "$env:USERPROFILE\Yandex.Disk\Exchange"
   pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1   # по желанию, MCP
   ```
2. Сверить локальный `.env` с `.env.example` (особенно `VITE_DEV_SERVER_URL=http://127.0.0.1:5174`). При смене lock-файлов — `composer install` / `npm ci`; при новых миграциях — `php artisan migrate`.
3. Агент читает handoff → этот файл → `AGENTS.md` и кратко сообщает, что актуально.

> Я.Диск дублирует файлы из git для Obsidian; **источник правды — git**. Без `git pull` vault может быть свежее handoff в репозитории на другой машине, но код — нет.

---

## Протокол Аудит

Read-only проверка проекта (механика + 3 субагента: архитектура, безопасность, качество).

| Действие | Команда |
| --- | --- |
| Полный аудит в Cursor | Напишите **Протокол Аудит** или `/audit-protocol` |
| Только скрипт | `pwsh -File scripts/audit-protocol.ps1` |
| С PHPUnit | `pwsh -File scripts/audit-protocol.ps1 -RunTests` |

Промпты: `agents/audit/` + vendor **`agents/rdudov/`** ([rdudov/agents](https://github.com/rdudov/agents): 05 architecture reviewer, 09 code reviewer, §7 безопасность).  
Обновить rdudov: `pwsh -File scripts/sync-rdudov-agents.ps1`.  
Subagent Cursor: **security-review** (полный протокол).  
Skill: `.cursor/skills/audit-protocol/SKILL.md`.  
Backlog: `docs/sync/v5-local-Components-Code-Audit-2026-07.md`.  
Отчёты сессии: `docs/audit-reports/` (локально, в gitignore).

## Протокол Архитектура

Оценка **изящности**, границ модулей, дубли, автономность scripts (**offline**, без сети).

| Действие | Команда |
| --- | --- |
| Полная оценка в Cursor | **Протокол Архитектура** или `/architecture-protocol` |
| Только скрипт | `pwsh -File scripts/architecture-protocol.ps1` |

Промпты: `agents/architecture/` + rdudov **04** + **05**. Skill: `.cursor/skills/architecture-protocol/SKILL.md`.  
Отчёты: `docs/architecture-reports/` (gitignore). Шкала **A–D**.

---

## Быстрая фраза для нового чата

Скопируйте в Cursor:

```
ЗАБРАТЬ
```

или развёрнуто:

```
Перед работой: git pull, прочитай docs/sync/Cursor-handoff-latest.md и docs/sync/cursor-agent-startup.md, сверься с AGENTS.md. Не начинай правки без контекста из handoff.
```

---

*Обновлено: 2026-06-02.*
