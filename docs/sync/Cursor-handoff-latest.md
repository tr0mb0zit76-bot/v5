# Cursor handoff — CRM v5 (для второго ПК)

> **Синхронизация:** Yandex Disk `Exchange/CRM/` · **Код:** `git pull` в `v5.local` · **Не через git:** Obsidian vault, `~/.cursor/mcp.json` (prod-токен).  
> Источник в git: `docs/sync/Cursor-handoff-latest.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

**Обновлено:** 2026-06-18 · **HEAD:** см. `git log -1` после pull

---

## Быстрый старт на «большом» компьютере

1. `git clone` / `git pull` → `C:/OSPanel/home/v5.local` (или свой путь)
2. `composer install`, `npm ci`, `npm run build` (или `composer run dev`)
3. Свой `.env` (не копировать с ноута)
4. Obsidian vault: открыть `YandexDisk/Exchange` (тот же аккаунт Я.Диска)
5. Cursor MCP:
   - Проект: `for_note/cursor-mcp.project.json` → `.cursor/mcp.json`
   - Глобально: скопировать `mcp.json` с другой машины **или** выпустить tokens заново
   - `pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1` — Obsidian MCP (порт 27200)
6. Документация vault: `pwsh -File scripts/sync-docs-to-yandex.ps1`
7. Прод после pull: `php artisan migrate`, `npm run build`; модуль растаможки — `php artisan import-cost:sync-references`; при смене логики оплат — `php artisan payment-schedules:sync-settlement-amounts`

7. Прод после pull: `php artisan migrate`, `npm run build`; модуль растаможки — `php artisan import-cost:sync-references`; при смене логики оплат — `php artisan payment-schedules:sync-settlement-amounts`

---

## Что сделано недавно

### Управленка: план/факт, наличные, split (2026-06-18)

- **Снапшоты бюджета:** `BudgetPlanSnapshotService`, таблицы `budget_plan_snapshots` / `budget_plan_snapshot_lines`
- **Бюджетирование:** кнопка «Зафиксировать план» → `POST /budgeting/plan-snapshots`
- **План vs факт:** `BudgetVarianceService`, таблица отклонений на Index управленки (`plan_source: snapshot` | `live`)
- **Ручные операции:** модалка «Добавить операцию» (наличные без выписки)
- **Split:** один платёж → несколько строк графика (`management_statement_line_splits`, Reconcile)
- Документация: `management-accounting-budgeting-integration.md`, `management-accounting-implementation-plan.md` (v1.3)
- **Прод:** `php artisan migrate`, `npm run build`

### Модуль «Растаможка» (`530c6be`)

- **Маршрут:** `/modules/import-cost`, область `modules_import_cost`
- Расчёт продажной цены: инвойс + ТН ВЭД → пошлина, НДС, таможенный сбор, утильсбор (ПП № 1291), доставка; округление до целых ₽
- Справочники: ЕЭК OData + таблицы `import_cost_*`; `php artisan import-cost:sync-references` (cron пн 03:15)
- Документация: `docs/import-cost-calculator-architecture.md`, [[v5-local/Components/Import Cost Calculator]]
- **Прод:** после migrate — `import-cost:sync-references`, включить область в ролях, `npm run build`

### Лимиты загрузки документов (`9d62bd2`, `fffe87f`, `087c274`)

- Разделены `policy_max_bytes` и `server_upload_max_bytes`; оценка страниц PDF на сервере
- На проде: PHP-FPM `upload_max_filesize = 128M`, `poppler-utils`

### AG Grid (`785cabe`)

- Восстановлен `resolveAgGridViewportHeight()` на 9 гридах

### Колокольчик уведомлений (`911128f`)

- В свёрнутом сайдбаре панель открывается вправо, шире

### OCR / pngquant (`a97b035`)

- `pngquant` в Docker OCR, fallback Ghostscript

### Ранее (июнь)

- Playbook БП, избранное меню, дашборд по подразделению
- Документы: ТН/ЭТрН/CMR/ТСД одной группой; наличка — без закрывающих в чек-листе
- Управленка: входящие по сумме, частичные оплаты, наличка → срок от выгрузки

---

## Карта знаний

| Что | Где |
|-----|-----|
| Индекс vault | [[00-index]] |
| Компоненты кода | [[v5-local/00-index]] |
| Changelog | [[Changelog/2026-06]] |
| **Растаможка** | git `docs/import-cost-calculator-architecture.md` |
| Документы (инструкция) | git `docs/documents-user-guide.md` |
| MCP tools | git `docs/mcp-crm-instructions.md` |
| Roadmap | git `docs/roadmap-2026.md` |
| Управленка | git `docs/management-accounting-architecture.md` |
| План vs факт | git `docs/management-accounting-budgeting-integration.md` |
| Компонент управленки | git `docs/sync/v5-local-Components-Management-Accounting.md` |
| График оплат | git `docs/payment-schedule-architecture.md` |
| Синхрон индексов | git `docs/sync/README.md` |

---

## Прод (crm.avtoaliyans.ru)

```bash
git pull
php artisan migrate
php artisan import-cost:sync-references   # после деплоя растаможки
npm run build
php artisan optimize:clear
```

- Документы в Книге: `php scripts/mcp-prod-upsert-documents.php`
- После правок графика/разнесения: `php artisan payment-schedules:sync-settlement-amounts`
- Роли: включить `modules_import_cost` для менеджеров продаж спецтехники

---

## Коммиты для ориентира

```
530c6be Модуль «Растаможка»: ЕЭК OData, ПП № 1291 и калькулятор для продажной цены
9d62bd2 Разделить лимит политики и PHP upload_max_filesize для документов
a97b035 Исправить сжатие PDF: pngquant и безопасный уровень optimize
fffe87f Точный лимит вложений PDF через серверную оценку страниц
785cabe AG Grid: viewport height на гридах
911128f Колокольчик уведомлений в collapsed sidebar
```
