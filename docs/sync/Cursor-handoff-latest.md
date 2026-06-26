# Cursor handoff — CRM v5 (для второго ПК)

> **Синхронизация:** Yandex Disk `Exchange/CRM/` · **Код:** `git pull` в `v5.local` · **Не через git:** Obsidian vault, `~/.cursor/mcp.json` (prod-токен).  
> Источник в git: `docs/sync/Cursor-handoff-latest.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

**Обновлено:** 2026-06-02 · **Ветка:** `master` · **HEAD:** _(после push — см. `git log -1`)_

**Между ПК:** напиши агенту **ОТДАТЬ** (конец сессии) или **ЗАБРАТЬ** (старт на другом ПК) — см. `docs/sync/cursor-agent-startup.md`.

---

## Инструкция для агента Cursor (читать первым)

Полный регламент: **`docs/sync/cursor-agent-startup.md`** (копия на Я.Диске: `CRM/cursor-agent-startup.md`).  
Автоправило в репозитории: **`.cursor/rules/project-context-handoff.mdc`**.

**Перед правками кода:**

1. `git pull` (на втором ПК — обязательно) или команда **ЗАБРАТЬ**.
2. Прочитать: этот handoff → `cursor-agent-startup.md` → `AGENTS.md` (домен).
3. По теме — карточку `docs/sync/v5-local-Components-*.md`.
4. После pull: `pwsh -File scripts/sync-docs-to-yandex.ps1` (с `-ExchangeRoot`, если vault не в `C:\Sync\...`).

**После заметной работы:** обновить этот файл + **ОТДАТЬ** (`commit`/`push` + `sync-docs-to-yandex.ps1`).

**Фраза для нового чата:** `ЗАБРАТЬ` (или развёрнуто — см. `cursor-agent-startup.md`).

---

## Что сделано недавно (2026-06-02) — наличка + ottn, график оплат

| Коммит | Суть |
| --- | --- |
| _(текущий)_ | **Наличка + `ottn`:** срок оплаты от `track_received_date_*` + сдвиг (не от выгрузки); реестр «Документы» разблокирован для clerk |
| `11a39bf` | Handoff, индексы, команды **ОТДАТЬ** / **ЗАБРАТЬ** |
| `4536232` | Скролл ag-Grid; дата получения на всех строках заявки+УПД |

**Нюанс налички**

- `cash` + **`fttn`** → по-прежнему срок от **выгрузки** (`PaymentScheduleCashBasis`).
- `cash` + **`ottn`** / **`fttn_receipt`** → как безнал: ручная дата получения, пересборка `payment_schedules`.
- Закрывающие слоты (УПД) при наличке **не создаются** — только заявка; дата одна на сторону.
- После деплоя на старых заказах (напр. **#28**): пересохранить дату получения, чтобы пересчитался `planned_date`.

Документация: `docs/payment-schedule-architecture.md`, `docs/sync/v5-local-Components-Documents-Registry.md`.

---

## Что сделано недавно (2026-06-02) — документы, гриды, прод

| Коммит | Суть |
| --- | --- |
| `ed5dc12` | Экспорт ag-Grid в Excel (заказы, контрагенты); колонка «Дата получения» в таблице учёта документов |
| `6a9c4ae` | Дата получения встроена в строки учёта (не отдельные строки) |
| `6f51df9` | Делопроизводитель правит `track_received_date_*` из реестра «Документы» |
| `a01ba05` | Оптимизация логотипов CRM; первый фикс горизонтального скролла ag-Grid |
| `4536232` | Точный скролл без «пустого хвоста»; дата получения на всех строках заявки+УПД одной стороны |

**Дата получения оригиналов**

- Одно поле на сторону: `track_received_date_customer`, `track_received_date_carrier`.
- Нужна при базисах `ottn` / `fttn_receipt` в графике, **включая наличку** при этих базисах; при `cash` + `fttn` — нет.
- Редактирование: роль **clerk** + admin — реестр `/documents` и таблица «Учёт документов» в мастере.
- Карточка кода: `docs/sync/v5-local-Components-Documents-Registry.md`.

**ag-Grid горизонтальный скролл**

- `agGridHorizontalScroll.js`, `useAgGridHorizontalPanel.js` — ширина = сумма колонок; `min-width` только у тела, не хедера.

**На проде:** после `git pull` + `npm run build` — для заказов cash+ottn пересохранить дату получения.

```powershell
git pull
npm run build
php artisan config:cache
php artisan route:cache
pwsh -File scripts/sync-docs-to-yandex.ps1
```

---

## Быстрый старт на ноутбуке

1. **Git + SSH**
   ```powershell
   git clone git@github.com:tr0mb0zit76-bot/v5.git C:\OSPanel\home\v5.local
   cd C:\OSPanel\home\v5.local
   git checkout master
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

4. **Тестовая БД:** скопировать `u_tromb.env.example` → `.env.testing`, **`DB_DATABASE=u_tromb_test`** (рабочая `u_tromb` не трогать). После pull с миграциями:
   ```powershell
   php artisan migrate --env=testing --schema-path=database/schema/.skip-mysql-cli-load
   ```
   **Не задавать `DB_HOST` в PowerShell** перед тестами — перебьёт `.env.testing`.  
   `phpunit.xml`: `ORDER_WIZARD_TEST_DATABASE=u_tromb_test`.

5. **Obsidian vault:** `YandexDisk/Exchange` (тот же аккаунт Я.Диска)

6. **Cursor MCP:** `for_note/cursor-mcp.project.json` → `.cursor/mcp.json` или токены заново

7. **Синхрон индексов vault** (после каждого `git pull` или команды **ЗАБРАТЬ**):
   ```powershell
   pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "$env:USERPROFILE\Yandex.Disk\Exchange"
   pwsh -File scripts/sync-cursor-mcp-from-yandex.ps1
   ```

8. **Cursor:** правило `.cursor/rules/project-context-handoff.mdc` подтягивается из git; инструкция — `docs/sync/cursor-agent-startup.md`

9. **После pull с commercial roadmap / master:**
   ```powershell
   php artisan migrate
   php artisan db:seed --class=ProposalHtmlTemplateVariableSeeder
   npm run build
   php artisan optimize:clear
   ```

---

## Что сделано недавно (2026-06-02)

### Печать, лиды, флот, MCP

| Коммит | Суть |
| --- | --- |
| `067ca7c` | Поиск задач MCP/агента по **имени ответственного**; docs лидов (`leads-mechanism.md`, `lead-user-guide.md`); legacy `gosnomer` + прицеп в печати; ошибки валидации в `VehicleWizard` |
| `e4d9168` | Плейсхолдер **`gosnomer_TS`** → `vehicle.number` (legacy + игнор маппинга «на себя») — заказ #58, шаблон `zayavka_s_zakazom_RF_AS` |
| `78e7e0a` | **`FleetVehicleRegistry`** — дедуп ТС по владельцу + госномеру (UI «Авто», MCP, портал); в БД шаблонов не сохраняются `placeholder → placeholder` |

**Печать — важно для агента:**

- UI шаблонов показывает **итоговое** сопоставление (`effectiveVariableMapping`), генерация DOCX читает **сырой** `settings.variable_mapping` из БД.
- После правки DOCX или сидера проверить `gosnomer_TS`, `gosnomer` и т.п. — явно выбрать «Транспорт: Номер» и **Сохранить** шаблон.
- ТС/водитель в снимке берутся из `performers` / `order_legs.metadata.performer` (`fleet_vehicle_id`, `fleet_driver_id`), не из `orders.driver_id`.
- Каталог плейсхолдеров: `PrintFormVariableCatalog.php`, резолвер: `PrintFormPlaceholderPathResolver.php`.

**Редактирование ТС:** раздел **Авто** (`/fleet/vehicles`), двойной клик → `VehicleWizard` → Сохранить. Нужна область роли **`drivers`**. Из мастера заказа — только выбор из списка.

**Дубли ТС на проде:** #49 / #50 (одинаковый `С357ХК797`, владелец #98) — артефакт до дедупа; #50 можно удалить, если ни один заказ не ссылается. Заказ #58 использует **#49**.

**Лиды (nudges, бриф):** коммит `adafd54` — движок nudges, `LeadAttentionQueueService`, настройки БП; полное описание: `docs/leads-mechanism.md`.

**На большом ПК после pull:**

```powershell
git pull
npm run build
php artisan optimize:clear
pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "$env:USERPROFILE\Yandex.Disk\Exchange"
```

**На проде после pull (уже задеплоено 2026-06-02):** `git pull`, `npm run build`, `php artisan optimize:clear`. Миграций в этих коммитах нет.

---

## Что сделано недавно (2026-06-23)

### Собственный парк, рейсы, локальная БД, прод

| Коммит | Суть |
| --- | --- |
| `bd8894c` | «Собственный парк» — виртуальный перевозчик (`is_own_company=false`), не в списке own company; ag-Grid scroll + filter persistence; PHPUnit → **`u_tromb_test`** |
| `078b41d` | Мастер заказа: один пункт «Собственный парк» (без дубля в поиске); выбор всегда ставит `execution_mode=own_fleet` |

**Рейсы (`/fleet/trips`):** строки только из `fleet_trips`. Создание при сохранении заказа, если в `performers` есть **`execution_mode: own_fleet`** — не достаточно одного `carrier_id=Собственный парк`. Карточка: `docs/sync/v5-local-Components-Fleet-Own-Fleet.md`.

**Прод (ручные правки БД, июнь 2026):**
- Удалён лишний рейс у **АС-2606-0001** (внешний перевозчик, рейс остался после смены).
- **СП-2606-0003** — рейс #5 + `execution_mode=own_fleet` в costs/metadata (мастер не сохранял из‑за рассинхрона AS/СП).

**Локально:** без `.env.testing` тесты били в `u_tromb` и сносили данные; после восстановления дампа — `DecryptException` на `mail_imap_secret` при несовпадении `APP_KEY` (обнулить секреты или вернуть ключ из источника дампа).

**На большом ПК после pull:**
```powershell
git pull
copy u_tromb.env.example .env.testing   # если ещё нет; DB_DATABASE=u_tromb_test
php artisan migrate --env=testing --schema-path=database/schema/.skip-mysql-cli-load
npm run build
pwsh -File scripts/sync-docs-to-yandex.ps1
```

---

## Что сделано ранее (2026-06-22)

### Шаблоны КП — GrapesJS + демо Unisender

| Коммит | Суть |
| --- | --- |
| `146a1de` | Визуальный редактор **GrapesJS** (`grapesjs-preset-newsletter`), preview на лиде в редакторе |
| `5272b6e` | Демо-шаблон «Параллельный импорт» (`parallel-import-demo`), seeder `ProposalHtmlTemplateDemoSeeder` |
| `ebc28d3` | Печать заказа: `{route_point_row_special_conditions}` из `wizard_state.performers` |
| `f374d2a` | Пункт меню «Шаблоны КП», синхронизация статуса лида с close outcome |

**Как проверить модуль КП:**

1. **Модули → Шаблоны КП** (область `modules_proposal_templates` + доступ к настройкам).
2. Открыть **«Параллельный импорт (демо Unisender)»** — макет как в Unisender; переменные `{responsible.*}`, `{counterparty.contact_person}` и др.
3. В редакторе: **слева** панель переменных, **справа** холст GrapesJS; после сохранения — Preview на лиде.
4. На карточке лида: вкладка коммерции → HTML-шаблон → preview / PDF.

**После pull:**

```powershell
git pull
npm ci
npm run build
php artisan db:seed --class=ProposalHtmlTemplateDemoSeeder
php artisan optimize:clear
```

### Ранее 2026-06-22

- **`d0880d4`** — создание лидов из задач (`from_task`, `link_task_id`), кнопки в разделе «Задачи», фикс `ReferenceError: lead is not defined` в `Leads/Wizard.vue`
- **`3b2c73b`** — merge commercial roadmap в `master`, обновление handoff/индексов
- Инструкция старта сессии Cursor: `docs/sync/cursor-agent-startup.md`, правило `.cursor/rules/project-context-handoff.mdc`

---

## Что сделано ранее (2026-06-21) — Commercial roadmap steps 1–5

Коммит **`09b920f`**, влито в **`master`** (`3b2c73b`).

| Шаг | Суть |
| --- | --- |
| **1** | Портрет контрагента из лида — merge + preview, UI в Wizard |
| **2** | Персона «Почта», `MailThreadAnalysisService`, tools в command bar |
| **3** | HITL `contractor_insight_drafts` — черновики инсайтов, accept/reject |
| **4** | HTML-шаблоны КП → PDF (Gotenberg) + GrapesJS в Модулях + демо `parallel-import-demo` |
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
| **Старт сессии Cursor** | `docs/sync/cursor-agent-startup.md` |
| **Правило агента** | `.cursor/rules/project-context-handoff.mdc` |
| **Лиды (механизм, nudges)** | `docs/leads-mechanism.md`, `docs/lead-user-guide.md` |
| **Документы / track received / Excel** | `docs/sync/v5-local-Components-Documents-Registry.md` |
| **Fleet / рейсы / дедуп ТС** | `docs/sync/v5-local-Components-Fleet-Own-Fleet.md` |
| **ОТДАТЬ / ЗАБРАТЬ между ПК** | `docs/sync/cursor-agent-startup.md` |
| **Граф знаний vs Obsidian** | `docs/sync/knowledge-graph-notes.md` |
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

**SSH:** `docs/sync/prod-ssh.md` — IP **`91.229.11.16`**, ключ **`C:\,ssh\private_key.ppk`** (PuTTY PPK), скрипт `scripts/prod-plink.ps1`. Путь на сервере: `/var/www/www-root/data/www/avtoaliyans.ru` (не `crm.avtoaliyans.ru` как подпапка).

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

1. Проверить заказ **#28**: после пересохранения даты получения — `planned_date` перевозчика от ottn, не от выгрузки
2. `OrderDocumentsModal` — опционально те же поля track received
3. Ссылка «Открыть карточку ТС» из мастера заказа; merge дубля #50 → #49 на проде
4. Fleet: автоудаление `fleet_trips` при смене перевозчика на внешнего
