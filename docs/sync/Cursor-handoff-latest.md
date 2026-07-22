# Cursor handoff — CRM v5 (для второго ПК)

> **Синхронизация:** Yandex Disk `Exchange/CRM/` · **Код:** `git pull` в `v5.local` · **Не через git:** Obsidian vault, `~/.cursor/mcp.json` (prod-токен).  
> Источник в git: `docs/sync/Cursor-handoff-latest.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

**Обновлено:** 2026-07-22 19:25 · **Ветка:** `master` · **тема:** зарплата (UI + закрытие удалённых)

### Зарплата — дожим после `416b195f`

| Блок | Статус |
| --- | --- |
| Выплаченные начисления (`unpaid_amount≈0`) не в таблице заказов периода | ✅ |
| Soft-deleted заказы (EXWL-1) не в таблице; «К выплате» = остаток, не история | ✅ |
| UI: месяц + H1/H2 + создать; период/сотрудники/подразделения на одной строке (мультивыбор) | ✅ |
| `salary:settle-removed-order {orderId}` — закрыть начисление по удалённому заказу | ✅ |
| Портал / RFQ / УУ linked pairs | ⏳ локально, **не** в этом коммите |

**На прод после pull + build:**

```text
git pull
npm run build
php artisan optimize:clear
php artisan salary:settle-removed-order 1
# dry-run: php artisan salary:settle-removed-order 1 --dry-run
```

EXWL-1 = order id **1** (soft-deleted на проде). АС-2606-0001 с нулевым unpaid после деплоя `416b195f`+этого коммита из таблицы пропадёт сам.

**Следующий шаг:** деплой зарплаты; settle EXWL-1; либо коммит портала/RFQ/УУ.

---

**Обновлено (архив):** 2026-07-22 15:58 (ОТДАТЬ) · **HEAD:** `78a1400` · **Ветка:** `master`

### Итог сессии 2026-07-22 вечер (ОТДАТЬ)

| Блок | Статус |
| --- | --- |
| Наличка перевозчику: слот «Заявка перевозчику» **не** в обязательном чек-листе (PHP + JS зеркало) | ✅ |
| Претензии в мастере заказа: пустое состояние → «Нет претензий» | ✅ |
| Лид «Что дальше»: клик по задаче/контакту → скролл + фокус + подсветка «Следующий шаг»; кликабельны пробелы в «Ещё не хватает» | ✅ |
| БП «Знакомство» / spawn / card-focus / deal-кнопки (ранее в сессии) | ✅ уже в `becef4b` |
| Metrika cookies | ⏳ отложено |

**На прод / второй ПК (ЗАБРАТЬ):**

```text
git pull
npm run build
php artisan optimize:clear
```

Миграций в этом коммите нет (миграции БП «Знакомство» — в `becef4b`, если ещё не накатывали: `php artisan migrate --force` + `business-processes:seed-playbooks`).

**Не в git:** `templates/` (сертификат агента) — локальный мусор, не коммитить.

**Следующий шаг:** деплой + `npm run build`; демо лида / БП знакомство; либо УУ linked pairs / MCP Claims; Metrika cookies.
