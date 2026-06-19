# Cursor handoff — CRM v5 (для второго ПК)

> **Синхронизация:** Yandex Disk `Exchange/CRM/` · **Код:** `git pull` в `v5.local` · **Не через git:** Obsidian vault, `~/.cursor/mcp.json` (prod-токен).  
> Источник в git: `docs/sync/Cursor-handoff-latest.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

**Обновлено:** 2026-06-20 · **HEAD:** см. `git log -1` после pull

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
7. Прод после pull: `php artisan migrate`, `npm run build`, `php artisan optimize:clear`; растаможка — `php artisan import-cost:sync-references`; график оплат — при рассинхроне `php artisan payment-schedules:repair-settlement --order=ID`

---

## Что сделано недавно

### Печать: QR, verify, подпись/печать (2026-06-20)

- Размеры QR в `config/documents.php` (`PRINT_VERIFICATION_QR_*`), по умолчанию 80 px в DOCX
- `DocxVmlOverlayStylePatcher` — QR не забирает смещения подписи/печати
- Публичная `/verify/order-documents/{id}?code=…` **без auth**; контрагент по `metadata.party` (`PrintVerificationPageScope`)
- Отдельный QR на каждый `OrderDocument` (заказчик ≠ перевозчик)
- Документация: `docs/print-form-pdf-protection.md`, карточка `docs/sync/v5-local-Components-Print-Forms-Verification.md`
- Коммиты ориентира: `5337664`, `590c44f`, `bb0673f`, `1ae41cb`

### График оплат и разнесение (2026-06)

- После пересборки `payment_schedules` — relink журнала и `allocation_payment_schedule_id` (`a1459d7`, `65c9c7a`)
- Команда: `php artisan payment-schedules:repair-settlement --order=5`
- Грид «График оплат»: фикс TDZ в `CashFlowGrid.vue` (`56a8a9d`) — на проде нужен `npm run build`

### Управленка: план/факт, наличные, split (2026-06-18)

- **Снапшоты бюджета:** `BudgetPlanSnapshotService`, таблицы `budget_plan_snapshots` / `budget_plan_snapshot_lines`
- **Бюджетирование:** кнопка «Зафиксировать план» → `POST /budgeting/plan-snapshots`
- **План vs факт:** `BudgetVarianceService`, таблица отклонений на Index управленки (`plan_source: snapshot` | `live`)
- **Ручные операции:** модалка «Добавить операцию» (наличные без выписки)
- **Split:** один платёж → несколько строк графика (`management_statement_line_splits`, Reconcile)
- Документация: `management-accounting-budgeting-integration.md`, `management-accounting-implementation-plan.md` (v1.3)

### Модуль «Растаможка» (`530c6be`)

- **Маршрут:** `/modules/import-cost`, область `modules_import_cost`
- Расчёт продажной цены: инвойс + ТН ВЭД → пошлина, НДС, таможенный сбор, утильсбор (ПП № 1291), доставка
- `php artisan import-cost:sync-references` (cron пн 03:15)
- Документация: `docs/import-cost-calculator-architecture.md`

### Ранее (июнь)

- Лимиты загрузки документов, AG Grid viewport, колокольчик в sidebar
- Playbook БП, документы ТН/ЭТрН/CMR/ТСД; наличка — без закрывающих в чек-листе
- Управленка: входящие по сумме, частичные оплаты

---

## Карта знаний

| Что | Где |
|-----|-----|
| Индекс vault | [[00-index]] |
| Компоненты кода | [[v5-local/00-index]] |
| Changelog | [[Changelog/2026-06]] |
| **QR / verify печати** | git `docs/print-form-pdf-protection.md` |
| **Растаможка** | git `docs/import-cost-calculator-architecture.md` |
| Документы (инструкция) | git `docs/documents-user-guide.md` |
| MCP tools | git `docs/mcp-crm-instructions.md` |
| Roadmap | git `docs/roadmap-2026.md` |
| Управленка | git `docs/management-accounting-architecture.md` |
| График оплат | git `docs/payment-schedule-architecture.md` |
| Синхрон индексов | git `docs/sync/README.md` |

---

## Прод (crm.avtoaliyans.ru)

```bash
git pull
php artisan migrate
npm run build
php artisan optimize:clear
php artisan import-cost:sync-references   # после деплоя растаможки
php artisan payment-schedules:repair-settlement --order=ID   # при рассинхроне оплат
```

- Документы в Книге: `php scripts/mcp-prod-upsert-documents.php`
- После правок графика/разнесения: `php artisan payment-schedules:sync-settlement-amounts`

---

## Коммиты для ориентира

```
1ae41cb Страница verify: party + без внутренних подсказок
bb0673f Убрать auth middleware со страницы verify
590c44f VML: подпись/печать не смещаются из-за QR
5337664 Уменьшить QR в печатных формах
65c9c7a Relink разноски выписки после resync графика
56a8a9d CashFlowGrid TDZ
a1459d7 Сохранение оплат графика после пересборки
530c6be Модуль «Растаможка»
```
