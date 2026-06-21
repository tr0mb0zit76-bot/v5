# Cursor handoff — CRM v5 (для второго ПК)

> **Синхронизация:** Yandex Disk `Exchange/CRM/` · **Код:** `git pull` в `v5.local` · **Не через git:** Obsidian vault, `~/.cursor/mcp.json` (prod-токен).  
> Источник в git: `docs/sync/Cursor-handoff-latest.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

**Обновлено:** 2026-06-21 · **Ветка:** `feature/commercial-roadmap-steps-1-5` · **HEAD:** `09b920f`

---

## Быстрый старт на ноутбуке

1. **Git + SSH**
   ```powershell
   git clone git@github.com:tr0mb0zit76-bot/v5.git C:\OSPanel\home\v5.local
   cd C:\OSPanel\home\v5.local
   git checkout feature/commercial-roadmap-steps-1-5
   git pull
   ```
   SSH-ключ: `C:\Users\<вы>\.ssh\id_ed25519.pub` → GitHub → Settings → SSH keys.  
   **Windows + кириллица в профиле:** использовать PowerShell, не Git Bash для `git push`:
   ```powershell
   git config --global core.sshCommand "C:/Windows/System32/OpenSSH/ssh.exe"
   & "C:\Windows\System32\OpenSSH\ssh.exe" -T git@github.com
   ```
   Ожидаемо: `Hi tr0mb0zit76-bot! You've successfully authenticated...`

2. **Зависимости:** `composer install`, `npm ci`, `npm run build` (или `composer run dev`)

3. **`.env`** — свой на каждой машине (не копировать с другого ПК). OSPanel MySQL: **`DB_HOST=127.0.1.21`**, не `127.0.0.1`.

4. **Тестовая БД:** скопировать шаблон → `.env.testing` (`u_tromb.env.example`). После pull с миграциями:
   ```powershell
   php artisan migrate --env=testing
   # при «битой» схеме:
   php artisan migrate:fresh --env=testing --force
   ```

5. **Obsidian vault:** `YandexDisk/Exchange` (тот же аккаунт Я.Диска)

6. **Cursor MCP:** `for_note/cursor-mcp.project.json` → `.cursor/mcp.json` или токены заново

7. **Синхрон индексов vault:**
   ```powershell
   pwsh -File scripts/sync-docs-to-yandex.ps1
   pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1
   ```

8. **После pull с commercial roadmap:**
   ```powershell
   php artisan migrate
   php artisan db:seed --class=ProposalHtmlTemplateVariableSeeder
   npm run build
   php artisan optimize:clear
   ```

---

## Что сделано недавно (2026-06-21) — Commercial roadmap steps 1–5

Коммит **`09b920f`**, ветка **`feature/commercial-roadmap-steps-1-5`** (на GitHub; PR в `master`).

| Шаг | Суть |
| --- | --- |
| **1** | Портрет контрагента из лида — merge + preview, UI в Wizard |
| **2** | Персона «Почта», `MailThreadAnalysisService`, tools в command bar |
| **3** | HITL `contractor_insight_drafts` — черновики инсайтов, accept/reject |
| **4** | HTML-шаблоны КП → PDF (Gotenberg) + CRUD в Модулях |
| **5** | Аналитика скриптов Play, A/B узлов, context tags, CSV export |

**Карта кода:** `docs/sync/v5-local-Components-Commercial-Roadmap.md`  
**ТЗ по шагам:** `docs/tz-step-01` … `docs/tz-step-05`, сводка `docs/commercial-roadmap-implementation-tz.md`

**Новые миграции (обязательны):**
- `2026_06_21_222758_create_contractor_insight_drafts_table`
- `2026_06_21_230000_create_proposal_html_templates_table`
- `2026_06_21_240000_add_ab_and_context_to_sales_scripts`

**Тесты roadmap (21 шт.):** см. карточку Commercial Roadmap — гонять **по файлам**, не одной командой (конфликт `schemaDropMany` vs `RefreshDatabase` в shared `u_tromb`).

---

## Что сделано ранее (2026-06-20)

### Печать: QR, verify, подпись/печать

- Размеры QR в `config/documents.php`, `DocxVmlOverlayStylePatcher`
- Публичная `/verify/order-documents/{id}?code=…` без auth
- Документация: `docs/print-form-pdf-protection.md`

### График оплат, управленка, растаможка

- `payment-schedules:repair-settlement`, CashFlowGrid TDZ fix
- Снапшоты бюджета, split, plan vs fact
- Модуль `/modules/import-cost`

---

## Карта знаний

| Что | Где |
| --- | --- |
| **Handoff (этот файл)** | `docs/sync/Cursor-handoff-latest.md` |
| **Commercial roadmap 1–5** | `docs/sync/v5-local-Components-Commercial-Roadmap.md` |
| Индекс vault | [[00-index]] |
| Компоненты кода | [[v5-local/00-index]] |
| Changelog | [[Changelog/2026-06]] |
| QR / verify печати | `docs/print-form-pdf-protection.md` |
| Скрипты (редактор + аналитика) | `docs/sales-scripts-editor-guide.md`, `docs/scripts-module-implementation-plan.md` |
| MCP tools | `docs/mcp-crm-instructions.md` |
| Roadmap | `docs/roadmap-2026.md` |
| Синхрон индексов | `docs/sync/README.md` |

---

## Прод (crm.avtoaliyans.ru)

```bash
git pull
php artisan migrate
php artisan db:seed --class=ProposalHtmlTemplateVariableSeeder   # после commercial step 4
npm run build
php artisan optimize:clear
php artisan import-cost:sync-references
php artisan payment-schedules:repair-settlement --order=ID   # при рассинхроне оплат
```

- HTML→PDF КП: `DOC_PREVIEW_DRIVER=gotenberg`, `GOTENBERG_URL=…`
- Документы в Книге: `php scripts/mcp-prod-upsert-documents.php`

---

## Коммиты для ориентира

```
09b920f Commercial roadmap steps 1-5 (portrait, mail, insights, HTML KP, script analytics)
b1ab68b Документация QR-проверки печати
5337664 QR в печатных формах
530c6be Модуль «Растаможка»
```

---

## Следующая сессия (предложение)

1. Merge PR `feature/commercial-roadmap-steps-1-5` → `master`
2. Deploy + migrate + seeder на прод
3. Проверить UI: портрет на лиде, insight drafts, шаблоны КП, Analytics в редакторе скриптов
4. Backlog: фаза 6.4 NLP, пользовательские инструкции в Книгу
