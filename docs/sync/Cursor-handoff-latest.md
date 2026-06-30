# Cursor handoff — CRM v5 (для второго ПК)

> **Синхронизация:** Yandex Disk `Exchange/CRM/` · **Код:** `git pull` в `v5.local` · **Не через git:** Obsidian vault, `~/.cursor/mcp.json` (prod-токен).  
> Источник в git: `docs/sync/Cursor-handoff-latest.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

**Обновлено:** 2026-06-30 · **Ветка:** `master` @ `b4499a1` · **Контекст:** CRM actions для скриптов/тренажёра + rubric analytics + artifact preview

**Между ПК:** напиши агенту **ОТДАТЬ** (конец сессии) или **ЗАБРАТЬ** (старт на другом ПК) — см. `docs/sync/cursor-agent-startup.md`.

---

## Что сделано недавно (2026-06-30) — скрипты → CRM, рубрики тренажёра, artifact preview

### Итог

- Добавлена миграция `lead_id` на `sales_script_play_sessions` без FK, по текущему паттерну `contractor_id/order_id`.
- `SalesScriptCrmActionService`: после завершения Play/Trainer создаёт заметку в лиде, обновляет `next_contact_at` и создаёт задачу, если в полях разговора есть `next_step_date`.
- Complete-форма в `SalesScripts/Play.vue` теперь принимает `lead_id` и `order_id`; при `order_id` связанный лид подтягивается через `orders.lead_id`.
- `TrainerRubricService`: рубрики `price`, `documents`, `conflict`, `upsell`, `discovery` с чек-листами критериев.
- В Play тренажёра показывается текущая рубрика оценки; в `TrainerAnalytics.vue` добавлен отчёт «По рубрикам» и колонка рубрики в последних сессиях.
- В `management-accounting-implementation-plan.md` убран устаревший backlog «второй банк»; оставлен только «другие форматы общей выписки». UID/счета/курсы не трогались.
- `.gitattributes`: добавлен `export-ignore` для dev/test/Cursor/docs/debug/probe путей.
- `scripts/build-prod-artifact.ps1`: dry-run проверяет, что `git archive` не включает dev-only пути; обычный режим создаёт tar-артефакт.

### Проверка

```powershell
vendor\bin\pint --dirty --format agent
php -l ...
pwsh -File scripts/build-prod-artifact.ps1 -DryRun
npm run build
```

`php artisan test --compact ...` локально по-прежнему упирается в отсутствие `mysql` CLI в `PATH` для schema dump.

---

## Что сделано недавно (2026-06-30) — скрипты продаж и тренажёр

### Итог

- `SalesScriptsDemoSeeder` расширен с 7 до 13 активных демо-сценариев.
- Добавлены сценарии: «Возврат уснувшего лида», «Переговоры по цене и марже», «Проблемный рейс / удержание клиента», «Повторная продажа действующему клиенту», «Тренажёр: цена и конкурент», «Тренажёр: конфликт и удержание».
- Добавлены поля разговора: `target_rate`, `decision_maker`, `required_documents`, `claim_reason`, `service_recovery_plan`, `own_fleet_argument`.
- `SalesAssistant/Trainer.vue`: добавлены профили покупателей для цены/конкурента, претензии по рейсу и расширения действующего клиента.
- `TrainerChatCompletionService`: первые реплики тренажёра для новых профилей стали жёстче и предметнее (цена/конкурент/обмен уступок, претензия/план восстановления, апсейл).
- Добавлен unit-тест `TrainerChatCompletionServiceTest`; `SalesScriptFlowTest` теперь фиксирует 13 активных сценариев, новые поля и минимум 3 тренажёрных сценария.

### Проверка

```powershell
vendor\bin\pint --dirty --format agent
php -l database\seeders\SalesScriptsDemoSeeder.php
php -l app\Services\SalesScripts\TrainerChatCompletionService.php
php -l tests\Feature\SalesScripts\SalesScriptFlowTest.php
php -l tests\Unit\TrainerChatCompletionServiceTest.php
php artisan db:seed --class=SalesScriptsDemoSeeder --no-interaction
php artisan sales:trainer-playground   # показал 13 сценариев / 13 активных версий
npm run build
```

`php artisan test --compact ...` локально заблокирован известной проблемой окружения Windows: `mysql` CLI не в `PATH`, `RefreshDatabase` не может загрузить schema dump.

---

## Что сделано недавно (2026-06-30) — гриды: контекстные меню и bulk actions

### Итог

- Коммит `b4499a1` запушен и задеплоен на прод (`git pull --ff-only`, затем `npm run build`).
- Добавлен общий composable `resources/js/Components/Grid/useGridContextMenu.js`.
- На общий слой переведены контекстные меню гридов: заказы, контрагенты, документы, задачи, лиды, водители/ТС собственного парка, диспозиция.
- В заказах сохранён пункт «Сводка по перевозке» / копирование в буфер.
- В графике оплат чекбоксы вынесены из ID в отдельную колонку выбора; фильтры режима сведены в select; bulk actions собраны в выпадающее меню с планом оплаты на сегодня / дату / снятием плана.
- На проде после сборки свежих OOM нет; swap `2G` активен.

### Проверка

```powershell
npm run build
```

---

## Что сделано недавно (2026-06-30) — прод: сборку убивал OOM

### Диагностика

- На проде `npm run build` периодически убивался kernel OOM-killer:
  `Out of memory: Killed process ... (node)` около 09:23, 09:26, 09:29, 09:38, 09:51.
- На сервере было `3.8GiB` RAM и **0B swap**.
- Дополнительно накопились 16 зависших процессов `php artisan mail:sync` вместе с родительскими `schedule:run`; каждый висел часами/днями и держал около `45MB` RSS.
- Причина накопления в коде: `Schedule::command('mail:sync')->everyTenMinutes()->withoutOverlapping(15)`. Если IMAP зависает дольше 15 минут, lock истекает и scheduler запускает новый sync.

### Что сделано на проде

- Остановлены зависшие `mail:sync` / `schedule:run`.
- Выполнен `php artisan schedule:clear-cache`.
- Добавлен и включён persistent swap:

```text
/swapfile none swap sw 0 0
```

- После этого `free -h`: RAM available около `1.9GiB`, swap `2.0GiB`.
- Контрольная `npm run build` на проде прошла успешно (`vite build`, ~7 секунд), свежих OOM в последнем окне после сборки нет.

### Кодовый предохранитель

- `routes/console.php`: `mail:sync` lock увеличен до `withoutOverlapping(60)`.
- `app/Console/Commands/SyncMailInboxesCommand.php`: добавлен `--time-limit`, default из `MAIL_SYNC_COMMAND_TIME_LIMIT_SECONDS` (`900` секунд).
- `config/mail_sync.php`: добавлены IMAP timeout env-настройки.
- `app/Support/MailSync/MailImapClient.php`: перед `imap_open` применяются `imap_timeout(...)`.
- Коммит `ee096ed` запушен и задеплоен на прод.

### Проверка

```powershell
vendor\bin\pint --dirty --format agent
php -l app\Console\Commands\SyncMailInboxesCommand.php
php -l app\Support\MailSync\MailImapClient.php
php -l config\mail_sync.php
php -l routes\console.php
php artisan schedule:list
```

Состояние после деплоя: `php artisan mail:sync --help` показывает `--time-limit`; зависшие старые sync-процессы убраны; новый `mail:sync` идёт по расписанию под timeout/lock.

---

## Что сделано недавно (2026-06-29) — прод: очистка runtime-мусора

### Итог

- Добавлен `scripts/prod-clean-runtime.sh`: удаляет старые `storage/framework/phpword-tmp/php*` старше 120 минут, `storage/app/tmp`, stale `*.tmp` в cache.
- Скрипт дополнительно чистит только **untracked** dev/probe артефакты (`test_*.php`, `tmp-*.php`, `scripts/debug-*.php`, `scripts/probe-*.php`, `scripts/verify-*.php`) через `git ls-files --others`, tracked код не трогает.
- На проде выполнена очистка: `storage/framework/phpword-tmp` уменьшился до `8K`, `storage/app/tmp` — `4K`.
- На проде добавлен cron root:

```cron
17 * * * * cd /var/www/www-root/data/www/avtoaliyans.ru && bash scripts/prod-clean-runtime.sh --quiet # prod-clean-runtime
```

### Что осталось сознательно не тронутым

- `node_modules`, `vendor`, `.git`, `tests`, `.cursor` остаются, пока прод работает как git working tree. Чтобы они не появлялись в принципе, нужен следующий шаг: artifact/sparse deploy вместо обычного `git pull` рабочей копии.
- После очистки untracked на проде остались: `deploy/ntfy/etc/server.yml`, `public/showcase-sla/carrier-offer.pdf`, `public/showcase-sla/customer-offer.pdf` — не удалялись автоматически.

---

## Что сделано недавно (2026-06-29) — модуль «Скрипты»: рабочие инструкции

### Итог

- `SalesScriptsDemoSeeder` теперь наполняет модуль не черновиками, а **7 рабочими инструкционными сценариями**: «Первичный запрос ставки», «Холодный звонок», «Знакомство», «Растём в бюджете», «Тренажёр», **«Дожим КП после отправки»**, **«Тендер / закупщик»**.
- Узлы переписаны в формате **цель шага → что сказать → что спросить → что зафиксировать → следующий шаг в CRM**.
- У каждого перехода есть `customer_label` — кнопки Play показывают живые варианты ответов клиента («Сначала скажите цену», «У нас уже есть перевозчик», «Давайте считать», «Пришлите список документов»), а не внутренние классы реакций.
- Расширены поля разговора (`route_from`, `route_to`, `loading_date`, `decision_deadline`, `email`, `decision_criteria`, `budget_window`, `next_step_date`, `volume_forecast`, `payment_terms` и др.), чтобы Play собирал данные для лида/заказа.
- Локальная БД засеяна:

```powershell
php artisan db:seed --class=SalesScriptsDemoSeeder --no-interaction
```

### Проверка

```powershell
vendor\bin\pint --dirty --format agent
php artisan test --compact tests/Feature/SalesScripts/SalesScriptFlowTest.php
```

Результат после добавления КП/тендера: **7 passed, 197 assertions**.

### Защита от отката к черновикам

`tests/Feature/SalesScripts/SalesScriptFlowTest.php` добавляет проверку, что:

- опубликовано 7 активных версий;
- в сидере есть «Дожим КП после отправки» и «Тендер / закупщик»;
- активные узлы не содержат черновых плейсхолдеров вида `[имя]` / `[..]`;
- каждый `{code}` в тексте имеет запись в `sales_script_capture_fields`;
- каждый переход опубликованного сценария имеет клиентскую реплику `customer_label`;
- сценарии содержат инструкционные узлы, а не только общие фразы.

На другом ПК после `git pull` достаточно выполнить:

```powershell
php artisan db:seed --class=SalesScriptsDemoSeeder --no-interaction
```

---

## Что сделано недавно (2026-06-29) — PHPUnit: RefreshDatabase + `u_tromb`

### Итог

| Метрика | Было | Стало |
| --- | --- | --- |
| Failed | ~82 → ~58 | **0** |
| Passed | ~1060 | **1141** |
| Skipped | — | 19 |
| Warning | — | 1 (не блокирует) |

Полный прогон (~159 с):

```powershell
php artisan migrate:fresh --env=testing --force   # при смене схемы / первый раз
php artisan test --compact                        # последовательно, не параллельно
```

**Не параллелить** suite — deadlock на schema dump. **Не задавать `DB_HOST` в PowerShell** — перебьёт `.env.testing` (OSPanel: `127.0.1.21`).

### Архитектура тестов

- Глобальный **`RefreshDatabase`** в `tests/TestCase.php` (БД из `.env.testing`, шаблон `u_tromb.env.example`).
- **`schemaDropMany()` в unit/feature** — убрать; MySQL не откатывает DDL в транзакции.
- **`payment_terms`** на заказах — **нет** в актуальной схеме → только `financial_terms.payment_terms_snapshot` (хелпер `createOrderWithPaymentTerms()`).
- **`carrier_rate` / `performers` / `carrier_payment_form`** на `orders` — проверять `Schema::hasColumn` перед insert/select.
- **FK:** не вставлять `user_id` / `template_id` / `scenario_id` «наугад» — factory или `insertGetId` родителя.
- **Seeded data:** миграции уже создают `transport-intake`, `kpi_deduction_rules`, departments 1–3, роли — тесты не дублировать slug/unique.

### Хелперы `tests/TestCase.php`

| Метод | Назначение |
| --- | --- |
| `onlyExistingOrderColumns()` | Фильтр атрибутов по колонкам `orders` |
| `insertOrderRow()` | INSERT заказа без несуществующих колонок |
| `assertDatabaseHasOrder()` | assert с фильтром колонок |
| `assertOrderCarrierRate()` | `orders.carrier_rate` или `financial_terms.contractors_costs` |
| `createOrderWithPaymentTerms()` | Условия оплаты в `financial_terms` |
| `createManagementBankAccount/StatementLine/ExpenseCategory()` | Управленка |
| `restoreTestDatabaseSchema()` | alias → `refreshTestDatabase()` |

### Типичные фиксы тестов (паттерны)

1. **`vat_22`** → `PaymentFormDictionary::defaultClientVatCode()` или `'vat'`.
2. **Лиды POST/PATCH** — обязателен `business_process_id` (seeded BP).
3. **`Role::create` с `name: manager`** — `firstOrCreate` / uniqid (unique на `roles.name`).
4. **Sales scripts editor** — `sales_assistant_scripts` в `visibility_areas`; redirect после graph save → `scripts.editor.versions.show`.
5. **Книга продаж / quiz analytics** — доступ: `canReadSalesBook` (свои попытки), `canViewAll` только admin/supervisor (`Role::hasRole('supervisor')`); отдельная страница `book.quiz-analytics`, не пропсы на `/book`.
6. **`DealTypeClassifier`** — через `app(DealTypeClassifier::class)`; категории KPI (`vat_zero_22`, `vat`), не legacy `direct`/`indirect`.
7. **`KpiDeductionRuleResolver` «unknown»** — `KpiDeductionRule::query()->delete()` перед assert (seeded rules).
8. **Public landing** — запросы на хост витрины: `http://v5.local/...` (`config('app.showcase_hosts')[0]`), не `localhost` (302).
9. **Roles** — `visibilityScopeOptions` = **3** (`own`, `department`, `all`); `documents.index` блокируется middleware `visibility.area.any:documents|orders` — без `orders` в areas будет 200.
10. **Disposition KPI** — заказ «в пути»: loading `actual_date` + unloading `actual_date = null` на route points.
11. **Print forms catalog** — `financial.carrier_norms_penalties.*`, не `carrier_norms_by_leg.N.*`.
12. **Salary payroll** — `payable_amount_computed` > 0 только при полной оплате заказчика (`paid_amount` на `payment_schedules` или ledger events).
13. **Import cost calculator** — в тесте `include_utilization_fee: false` или sync references; иначе 500 без `base_fee_rub`.
14. **Order closing docs notification** — `OrderDocumentObserver` уже вызывает `maybeNotify` при create waybill; в тесте проверять уведомление после create, не повторный `maybeNotify` (metadata `closing_documents_notified_at`).
15. **MCP / print templates** — при `PrintFormTemplate::create` нужны `entity_type`, `document_type`, `vue_component`, `source_type`.

### App fixes (не только тесты)

| Файл | Суть |
| --- | --- |
| `BackfillOrderOperationalData.php` | SELECT колонок через `Schema::hasColumn`; nullable `payment_terms`/`performers` |
| `BackfillContractorDefaults.php` | То же для SELECT из `orders` |
| `OneCFreshEtrnController` | Убран eager load несуществующего `driver` relation |
| `EtrnDraftBuilder` | Водитель из таблицы `drivers` (legacy), не Eloquent relation |
| `MailInboxSyncService` | `CarbonImmutable` → `Carbon` для `ActivityLedgerService::record` |
| `OrderClosingDocumentsNotificationService` | `loadMissing` без лишнего `refresh()` |
| `LeadController` | import `ConvertLeadRequest` (ранее) |
| `LeadRoutePriceBenchmarkService` | schema-aware колонки (ранее) |

### Скрипты автоматизации (следующий шаг)

| Скрипт | Назначение |
| --- | --- |
| `scripts/fix-order-schema-assertions.php` | `assertDatabaseHas` → `assertDatabaseHasOrder`, raw insert → `insertOrderRow` |
| `scripts/fix-order-wizard-inserts.php` | Правки wizard-тестов |

После массовых правок: `vendor/bin/pint --dirty --format agent`.

### На ноутбуке после **ЗАБРАТЬ**

```powershell
git pull
copy u_tromb.env.example .env.testing    # DB_DATABASE=u_tromb, DB_HOST=127.0.1.21
php artisan migrate:fresh --env=testing --force
php artisan test --compact
pwsh -File scripts/sync-docs-to-yandex.ps1 -ExchangeRoot "$env:USERPROFILE\Yandex.Disk\Exchange"
```

В новом чате Cursor: **«ЗАБРАТЬ»** или *прочитай `docs/sync/Cursor-handoff-latest.md` и продолжай миграцию тестов / скрипты*.

**Незакоммиченный diff** на основном ПК — перед `git pull` на ноутбуке нужен `git push` с большого ПК (или перенос patch).

---

## Что сделано недавно (2026-06-02) — разнесение СКЛ, заказ АС-ЗА-01 (#20)

| Коммит | Суть |
| --- | --- |
| `71feabd` | **Матчинг управленки:** короткие имена контрагентов (СКЛ), формат «СКЛ ООО» из выписки; relaxed-поиск по перевозчику для **исходящих** платежей; приоритет «контрагент в назначении» над «только сумма» |
| — | Расследование на **проде**: платежи 20 000 + 8 000 ₽ в СКЛ (счёт №154) разнесены **по категории**, не на график #6005 (заказ #20, 28 000 ₽) |

**Файлы:** `ManagementAccountingMatchingService.php`, `ManagementAccountingMatchingServiceTest.php`.

**Прод (данные, не код):** строки управленки **#6** (20k, 02.06) и **#42** (8k, 19.06) — переразнести **операционно** на `payment_schedule_id` **6005** (АС-ЗА-01). После `git pull` на проде — `php artisan optimize:clear` (фикс матчера уже заливали pscp до коммита).

**Следующий шаг:** переразнести два платежа в UI; при необходимости — доработать генерацию графика (два транша 20k+8k по тексту условий оплаты).

**Временные probe-скрипты** (`scripts/probe-order-20-settlement-prod.php` и др.) — **не в git**, только на сервере локально.

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
| `c09e7d2` | **Наличка + `ottn`:** срок оплаты от `track_received_date_*` + сдвиг (не от выгрузки); реестр «Документы» разблокирован для clerk |
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

4. **Тестовая БД:** скопировать `u_tromb.env.example` → `.env.testing`, **`DB_DATABASE=u_tromb`** (та же схема, изолированный инстанс; альтернатива — `u_tromb_test`). После pull с миграциями:
   ```powershell
   php artisan migrate:fresh --env=testing --force
   php artisan test --compact
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

**Тесты roadmap (21 шт.):** см. карточку Commercial Roadmap — гонять **по файлам**.  
**Актуально (2026-06-29):** полный suite **1141 passed** на `RefreshDatabase` + `u_tromb` через `.env.testing` — см. § 2026-06-29 выше.

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
| **PHPUnit / RefreshDatabase / u_tromb** | этот handoff § 2026-06-29, `tests/TestCase.php`, `u_tromb.env.example` |
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
