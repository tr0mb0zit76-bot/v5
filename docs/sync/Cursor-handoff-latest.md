# Cursor handoff — CRM v5 (для второго ПК)

> **Синхронизация:** Yandex Disk `Exchange/CRM/` · **Код:** `git pull` в `v5.local` · **Не через git:** Obsidian vault, `~/.cursor/mcp.json` (prod-токен).  
> Источник в git: `docs/sync/Cursor-handoff-latest.md` → `pwsh -File scripts/sync-docs-to-yandex.ps1`

**Обновлено:** 2026-07-06 11:42 · **HEAD:** `818722c` · **Ветка:** `master` · **Контекст:** Traklo external security hardening; SLA/APK на витрине, push-кнопки в Android, аудит HTTPS на prod; E2EE не нужен

**Между ПК:** напиши агенту **ОТДАТЬ** (конец сессии) или **ЗАБРАТЬ** (старт на другом ПК) — см. `docs/sync/cursor-agent-startup.md`.

---

## Следующий шаг (external users / Traklo)

1. **Переустановить свежий release APK** на телефонах (native push + кнопки «Прочитать» в шторке).
2. **Smoke на prod** — чеклист в `docs/traklo-runbook.md` (invite, 403 external, Traklo chat, порталы, push при свёрнутом приложении).
3. **Книга продаж:** опубликовать черновики **«Traklo для менеджера»** (id=111) и **«Traklo для контрагента»** (id=112), если нужен статус published.
4. Опционально: desktop-кабинет контрагента (отложено в ТЗ §2); SMS-invite; WebSocket вместо poll.

---

## Что сделано (2026-07-06) — Traklo external security hardening

- **Messenger API:** external больше не получает общие `document-chips`; `openDirect` для external разрешён только с уже связанными сотрудниками из counterparty conversations; `order_id` в сообщении counterparty-чата проверяется через `CounterpartyOrderAccess` по внешнему участнику.
- **Mobile shell:** общий `entity-chips` для external возвращает пустую выдачу; UI action sheet у external скрывает внутренние CRM-действия (ссылки на лид/заказ/контрагента, публичная transport-request ссылка), оставляет релевантный upload/phone.
- **Regression tests:** добавлены проверки в `CounterpartyMessengerTest` и `RejectExternalFromInternalRoutesTest`.
- **Проверка:** `vendor/bin/pint --dirty --format agent`, `php -l` изменённых PHP-файлов, `npm run build` — OK. `php artisan test --compact tests/Feature/CounterpartyMessengerTest.php tests/Feature/RejectExternalFromInternalRoutesTest.php` не стартует на этой машине: отсутствует `mysql.exe` в `PATH`/установке, Laravel schema dump не загружается.

---

## Что сделано (2026-07-05–06) — SLA, APK, push, безопасность

- **Витрина SLA:** третья плитка «Скачать приложение» на hub `/sla` (под «Для клиентов» / «Для перевозчиков»); прямая ссылка на `/downloads/traklo.apk`; footer-ссылка убрана (`937d897`, `2dfee73`).
- **Landing `/downloads/traklo` удалён** — только статический APK + `.htaccess` MIME; `PublicSiteController::resolveTrakloApkUrl()` нормализует legacy path.
- **Android push:** `TrakloFirebaseMessagingService` — action buttons «Прочитать» / «Открыть»; backend data-only FCM (`MobilePushService`); `firebase-messaging` в app module.
- **Prod:** deploy + `traklo.apk` на сервере (~5.8 MB, HTTP 200); `FCM_ENABLED` + credentials заполнены.
- **Безопасность (аудит prod):** HTTPS/HSTS/TLS 1.2–1.3 ок для задачи «защита от перехвата по сети»; **E2EE не планируем** — переписка с контрагентами должна анализироваться на сервере. `.env` на prod: права/владелец поправлены вручную. phpMyAdmin/SSH/nginx-заголовки — не трогали (осторожно с ISPmanager).

---

## Что сделано (2026-07-05) — push / deploy / Книга продаж

- **Git push:** `0f558c1` (фаза A docs), `9ea05e5` (`ensureParentPage` + `--ensure-parent` для upsert).
- **Prod deploy:** `git reset --hard origin/master`, миграции `2026_07_05_*`, `npm run build`, `optimize:clear` — HEAD `9ea05e5`.
- **Книга продаж (prod):** создан родитель «Руководство по CRM», дочерние страницы:
  - [Traklo для менеджера](https://crm.avtoaliyans.ru/sales-assistant/book?article_id=111)
  - [Traklo для контрагента](https://crm.avtoaliyans.ru/sales-assistant/book?article_id=112)
  - Команда: `php artisan sales-book:upsert-child-page --ensure-parent …`

---

## Что сделано (2026-07-05) — External users (фаза A — документация)

- **`docs/traklo-manager-guide.md`** — инструкция для менеджера (invite, чаты, порталы).
- **`docs/traklo-counterparty-guide.md`** — для контакта контрагента (Traklo vs guest-ссылка).
- **`docs/traklo-runbook.md`** — полный dev runbook + smoke; пояснение «desktop-кабинет ≠ MVP».
- **`scripts/mcp-prod-upsert-traklo.php`** — публикация в Книгу продаж через MCP.

---

## Что сделано (2026-07-05) — External users (фаза D)

- **Customer web portal:** `/portal/customer/{token}` — upload customer-слотов (заявка, закрывающие); `OrderCustomerPortalController`, `OrderCustomerPortalDocumentService`.
- **Staff API:** `POST orders/{order}/portal-invites/customer` → ссылка для заказчика.
- **UI staff:** `CustomerPortalInviteButton`, `OrderTrakloChatButton` в мастере заказа (заказчик + перевозчики).
- **Mobile:** deep-link `?conversation_id=` / `?counterparty_*` в `Messenger.vue`.
- **Витрина:** «Скачать Traklo» на `/transport-request`.
- **Тесты:** `OrderCustomerPortalTest` (3 passed).

---

## Что сделано (2026-07-05) — External users (фаза C)

- **Миграция:** `chat_messages.order_id`, `message_type`; `users.password` nullable для invite-flow.
- **Backend:** `CounterpartyConversationService` (thread на контрагента, system message по заказу, guards групп/direct), `CounterpartyOrderDocumentService`, расширен `MessengerController` / `MessengerService`.
- **API:** `messenger/counterparty-contacts`, `open-counterparty`, `counterparty-orders`; `mobile/shell/counterparty/orders/{order}/document-slots|documents`.
- **Frontend Traklo:** `Messenger.vue` — вкладки канала (Все/Команда/Контрагенты), список контрагентов, system messages, панель заказов в треде; external — только counterparty + orders/documents.
- **Тесты:** `CounterpartyMessengerTest` (+ fix import `DocumentUploadBudgetEstimateController` в `routes/web.php`).

---

## Что сделано (2026-07-05) — External users (фаза B MVP)

- **ТЗ §14** зафиксировано в `docs/external-counterparty-users-tz.md`.
- **Миграции:** `users.is_external`, `contractor_*`, `contractor_contacts.is_traklo_primary`, `external_user_invites`, роли `counterparty_*`, поля `conversations` для counterparty.
- **Backend:** `CounterpartyOrderAccess`, `ExternalUserProvisionService`, invite `/external/invite/{token}`, middleware `RejectExternalFromInternalRoutes`, API `mobile/shell/counterparty/orders`.
- **UI staff:** блок Traklo на вкладке «Портрет» контрагента (основной контакт + invite-link).
- **Витрина:** footer «Скачать Traklo» → `/downloads/traklo.apk`.
- **Runbook:** `docs/traklo-runbook.md`.
- **Тесты:** `ExternalUserProvisionTest`, `RejectExternalFromInternalRoutesTest`.

---

## Следующий шаг (Traklo / окружение) — архив

1. **Деплой на prod:** `npm run build` + tar исходников (web); APK — только при смене native/icon/version.
2. **Laravel Nightwatch** на prod — см. раздел «Окружение» ниже (внешний дашборд, не модуль CRM).
3. **Vitest** для `mobileMessageLinks.js` / push navigation — локально и в CI, не на эмуляторе.
4. **Push** о новой публичной заявке — опционально.

---

## Что сделано недавно (2026-07-05) — Traklo: единый парсинг + mobile lead draft

- **Единый серверный intake:** `TransportTextIntakeService` (LLM через `OrderIntakeSchema` + fallback `TransportIntakeHeuristicParser`) → `LeadIntakeMapper`. Используется в `LeadMessageIntakeService` и `OrderDocumentIntakeService::structureWithLlm`.
- **Mobile lead card:** убрана кнопка «Открыть в CRM» в `MobileEntityDetailSheet`; расширен summary (маршрут, груз, контакт, исходный текст, parser).
- **Черновик в Traklo:** `PATCH mobile/shell/leads/{lead}` — правка маршрута/груза/контактов без назначения публичной заявки; UI с полями и «Сохранить».
- Тесты: `tests/Unit/LeadIntakeMapperTest.php`, `TransportIntakeHeuristicParserTest.php`, доп. кейс в `MobileShellFeedTest`.

---

## Окружение (архитектор — согласовано / рекомендации)

| Слой | Что | Где |
| --- | --- | --- |
| **Nightwatch** | Мониторинг Laravel (exceptions, slow requests, failed jobs) | **Облачный дашборд** [nightwatch.laravel.com](https://nightwatch.laravel.com) — **не** модуль в админке CRM. Агент на prod шлёт телеметрию; алерты email/Slack. |
| **Vitest** | Unit-тесты JS-хелперов mobile | **Dev-машина + CI** — не Android-эмулятор и не APK |
| **Staging** | `staging.crm…` или отдельный vhost | Smoke перед prod |
| **CI (GitHub Actions)** | `pint`, `php artisan test`, `npm run build` | На каждый push/PR |
| **Playwright smoke** | login → dashboard / mobile shell | После деплоя staging |
| **Deploy artifact** | tar/release вместо git в working tree prod | Меньше сюрпризов на сервере |

**Prod (2026-07-05):** web + release APK с иконкой выложены пользователем ранее; **git:** `00247b0` (деплой web — после `npm run build` + tar).

---

## Следующий шаг (Traklo / лиды) — архив

1. ~~Парсинг текста → лид через LLM~~ — сделано (`TransportTextIntakeService`).
2. ~~Mobile карточка без CRM wizard~~ — сделано (draft PATCH + sheet).
3. ~~Ответственный message vs public~~ — без изменений.
4. Push — опционально.


## Что сделано недавно (2026-07-05) — Traklo: иконка APK из `resources/`

- Канон исходника: `resources/icon.png` (1024×1024), `resources/icon-foreground.png`; инструкция — `resources/README.md`.
- Генерация mipmap + splash: `npm run traklo:icons` (`tools/generate-traklo-icons.mjs` + `@capacitor/assets`, фон `#0F172A`).
- Первичный экспорт из текущих Android-ресурсов: `npm run traklo:icons:prepare` (`tools/prepare-traklo-icon-source.mjs`, `sharp`).
- Дальше: заменить `resources/icon.png` → `npm run traklo:icons` → `npm run traklo:apk:release` (+ bump `versionCode`).

---

## Что сделано недавно (2026-07-05) — Traklo: вкладка «Лиды»

- В нижней панели Traklo добавлена вкладка **«Лиды»** (только при области `leads`): кнопка **«Создать лид из текста»**, список **«Входящие заявки»**, бейдж с числом заявок.
- Из вкладки **Чаты** убраны блок intake и входящих заявок; из меню чата убрана «Создать лид из текста» (осталась «Ссылка на заявку на перевозку»).
- `useMobileShell.loadTab('leads')` → `loadTrakloLeads`.
- **Деплой prod (2026-07-05 ~13:15):** tar исходников → `/var/www/www-root/data/www/avtoaliyans.ru`, `optimize:clear`, `npm run build`, `optimize:clear`. APK не обновлялся. Проверены routes `mobile.shell.*`, `public.transport-request.*`.

---

## Что сделано недавно (2026-07-05) — Traklo intake из мессенджера

- Добавлена публичная форма `/transport-request` (`PublicTransportRequestController`, `Public/TransportRequest.vue`): внешний контакт без входа в CRM оставляет маршрут/груз/контакты, в CRM создаётся `Lead` с `source=traklo_public_request` и metadata `public_transport_request`.
- В mobile messenger action sheet добавлена кнопка **«Ссылка на заявку на перевозку»**: вставляет в composer текст и ссылку на публичную форму, чтобы менеджер мог отправить её в чат/переслать наружу.
- В форме есть throttle `10/min` и honeypot `website`; это не внешний кабинет и не доступ к CRM, а безопасный intake в лиды.
- Второй слой: во вкладке **Чаты** появился блок **«Входящие заявки Traklo»** (`GET mobile/shell/traklo-leads`) — показывает открытые публичные заявки, позволяет открыть detail sheet лида, вставить ссылку на лид в чат и позвонить по телефону из заявки.
- Видимость: пользователи с областью `leads` и scope `own` видят свои Traklo-заявки и неназначенные входящие заявки; чужие назначенные заявки не попадают в mobile feed. Summary лида разрешён для неназначенной входящей Traklo-заявки.
- Третий слой: во вкладке **Чаты** и в thread action sheet добавлена кнопка **«Создать лид из текста»**. Менеджер вставляет сообщение клиента из WhatsApp/Telegram/SMS, endpoint `POST mobile/shell/leads/from-text` создаёт `Lead` с `source=traklo_message_intake`, текущим ответственным, исходным текстом в metadata и простым распознаванием `из … в …`, `груз …`, телефона.
- UI создания группы в Traklo расширен: список участников теперь занимает доступную высоту до нижнего меню, кнопка создания закреплена снизу формы.
- Служебная учётка `cursor` скрыта из `messenger.colleagues`, поэтому не показывается коллегам и в выборе участников группы.
- Проверка: `vendor/bin/pint --dirty --format agent`; `php artisan test --compact tests/Feature/PublicTransportRequestTest.php`; `npm run build`.
- Доп. проверка второго/третьего слоя: `php vendor/bin/pint --dirty --format agent`; `php artisan test --compact tests/Feature/MobileShellFeedTest.php tests/Feature/MessengerTest.php`; `npm.cmd run build`.
- Деплой на prod выполнен вручную архивом исходников: распаковка в `/var/www/www-root/data/www/avtoaliyans.ru`, `php artisan optimize:clear`, `npm run build`, повторный `optimize:clear`; проверен route `mobile.shell.leads.from-text`.

---

## Что сделано недавно (2026-07-05) — email-шаблоны КП

- Модуль «Шаблоны КП» получил библиотеку cold email / КП-шаблонов под бренд **«Автоальянс-Смоленск»**: `ProposalHtmlTemplateColdEmailLibrary`.
- `ProposalHtmlTemplateDemoSeeder` теперь сидит 10 сценариев: параллельный импорт, экспорт в Китай, химия/спецгрузы, спецтехника/негабарит, температурные, сборные, быстрый расчёт ставки, follow-up после звонка, возврат уснувшего лида, follow-up после КП.
- Старый `ProposalHtmlTemplateParallelImportDemo` оставлен совместимым фасадом на новый шаблон `parallel-import-demo`.
- Шаблоны используют CRM-плейсхолдеры отправителя `{responsible.name}`, `{responsible.phone}`, `{responsible.email}` и обращение `{counterparty.contact_person}`; старый бренд «Логистические решения», `img.hiteml.com` и `МЕНЯЕМ_ИМЯ` из новой библиотеки убраны.
- Добавлен локальный SVG-пак `public/assets/proposal-emails/*.svg`; шапка писем использует логотип `/assets/favicon/favicon.svg`.
- После визуального сравнения с Unisender каркас переделан ближе к исходной композиции: ширина 660px, подпись менеджера слева, бренд справа, крупная типографика, двухколоночные блоки с иллюстрациями. SVG-пак текущий временный; нормальные иконки рисовать отдельно вне проекта и потом заменить.
- Проверка: `vendor\bin\pint --dirty --format agent`; `php artisan test --compact tests\Unit\Support\ProposalHtmlTemplateParallelImportDemoTest.php tests\Unit\Support\ProposalHtmlTemplateColdEmailLibraryTest.php`; `php artisan db:seed --class=ProposalHtmlTemplateDemoSeeder --no-interaction`; `php artisan test --compact tests\Feature\LeadProposalHtmlTemplateTest.php --filter=test_settings_user_can_open_template_editor_index`.

---

## Что сделано недавно (2026-07-04) — Android branding Traklo

- Название APK/launcher: `Traklo` (`android/app/src/main/res/values/strings.xml`, `capacitor.config.json`).
- Мобильный login screen также переименован в `Traklo`.
- Launcher icon: выбран вариант `messenger-icon-logistics.png`; PNG-ресурсы сгенерированы в `android/app/src/main/res/mipmap-*`, фон adaptive icon `#0F172A`.
- Обновления APK теперь не требуют ручного bump `.env`: `MobileAppUpdateController` сначала читает `public/downloads/traklo-update.json`, `.env` оставлен как emergency override (`MOBILE_APP_FORCE_CONFIG=true`).
- Генератор: `npm run traklo:apk:debug` / `npm run traklo:apk:release` → `cap sync`, Gradle build, копия APK в `public/downloads/traklo.apk`, manifest из `android/app/build.gradle`.
- APK игнорируется git (`/public/downloads/*.apk`), manifest можно деплоить вместе с кодом; сам APK надо положить на прод по тому же URL `/downloads/traklo.apk`.
- Проверка: `php artisan test --compact tests/Feature/MobileAppUpdateTest.php`, `npm run traklo:apk:debug`.
- Чтобы APK не тащил весь desktop CRM bundle, `capacitor.config.json` теперь использует `webDir: "public/capacitor"` с минимальным fallback `index.html`; реальная web-часть грузится с `server.url` (`https://crm.avtoaliyans.ru/mobile/messenger`).
- `npm run traklo:apk:*` больше не запускает `npm run build`; для APK нужен только native shell + remote URL. Debug APK после правки: ~32.66 MB вместо ~97 MB.
- Release-подпись внедрена: Gradle читает `android/traklo-release.properties` или env `TRAKLO_RELEASE_*`; локальные секреты игнорируются git (`android/keystores/*.jks`, `android/traklo-release.properties`).
- Сгенерирован локальный keystore `android/keystores/traklo-release.jks`. Его нельзя терять: все будущие обновления должны подписываться этим же ключом и иметь увеличенный `versionCode`.
- `npm run traklo:apk:release` успешно собрал signed release APK: `public/downloads/traklo.apk`, размер ~4.73 MB.
- После ошибки `INSTALL_PARSE_FAILED_NO_CERTIFICATES` генератор исправлен: release APK теперь явно подписывается через Android `apksigner` и затем проходит `apksigner verify`; нельзя копировать `app-release-unsigned.apk` как готовый APK.
- Проверка установки: на AVD `Avtoalyans_API_36` старая версия дала `INSTALL_FAILED_UPDATE_INCOMPATIBLE` из-за другой подписи; после `:app:uninstallRelease` команда `:app:installRelease` установила APK успешно.

### Messenger vs cabinet bell

- Сообщения messenger больше не создают `CabinetInAppNotification` / запись в `notifications`, поэтому не появляются в колокольчике полной CRM.
- Messenger unread count и mobile FCM push `chat_message` сохранены через `MobilePushService::notifyUsers()`.
- Проверка: `php artisan test --compact tests/Feature/MessengerTest.php tests/Feature/MobilePushServiceTest.php tests/Feature/CabinetInAppNotificationsTest.php`.
- Mobile search: в `Mobile/Messenger.vue` добавлен крестик очистки общего поиска чатов/коллег/CRM. Проверка: `npm run build`.

---

## Что сделано недавно (2026-07-04) — mobile polish + FCM prod

### FCM на prod
- В `.env` на проде исправлен placeholder: `FCM_CREDENTIALS=/var/www/www-root/data/www/avtoaliyans.ru/storage/app/firebase-service-account.json` (файл уже лежал в `storage/app/`).
- **Локально:** замени `/path/to/service-account.json` на `storage/app/firebase-service-account.json` (положи туда JSON из Firebase Console → Service accounts).

### Mobile upload — размер и оптимизация
- `MobileDocumentUploadWizard`: перед выбором заказа — клиентская проверка лимита (`assessDocumentUploadBudget`, те же props `document_upload_limits` / `document_optimize`, что в desktop).
- PDF, превышающий лимит при включённом OCR sidecar → bottom sheet `MobileDocumentOptimizeSheet` (серверная оптимизация через `documents.optimize-pdf`, как в desktop).
- На бэкенде по-прежнему `DocumentWithinPageBudget` при `POST documents.store`.
- Тест: `MobileDocumentUploadTest::test_mobile_rejects_oversized_document_by_page_budget`.

### Прочий polish плана
- Badge на вкладке **Документы** (число заказов «требуют внимания»).
- Убрана дублирующая кнопка «Обновить» (остался pull-to-refresh).
- В detail sheet задачи — **«Написать ответственному»** (`responsible_id` в mobile tasks API).
- **Link preview** из БД: `GET mobile/shell/link-preview?url=…` — номер заказа/лида вместо `#id`.
- **Detail sheet** lead/contractor: `GET mobile/shell/leads/{id}/summary`, `contractors/{id}/summary`.
- **Thread action sheet:** «Позвонить» в direct-чате; `other_user.phone` в API conversations.

- Проверка: `npm run build`, `php artisan test --compact tests/Feature/MobileDocumentUploadTest.php`.

---

## Что сделано недавно (2026-07-04) — mobile CRM shell phase 4–5

### Phase 4 — прикрепление файла к заказу
- `MobileDocumentUploadWizard`: progress bar загрузки (`onUploadProgress`), слоты «Требуют файл» / «Уже загружено», подписи сторон на русском, confirm при замене, `capture="environment"` для камеры.
- Из thread после upload — **автоотправка** сообщения со ссылкой на документ; с вкладок — picker «Отправить в чат».
- `presetOrderId`: из карточки заказа «Прикрепить документ» → выбор файла → сразу слоты заказа.

### Phase 5 — карточки без гридов
- **Detail sheet** `MobileEntityDetailSheet.vue`: tap по карточке → bottom sheet (заказ с чеклистом документов через `GET mobile/shell/orders/{id}/summary`, задача с ссылками на заказ/лид).
- **Заказы в feed:** `documents_pending_count`, `documents_total_count`, мини-строка на карточке.
- **Задачи:** `order_url`, `lead_url` в API; кнопки «Отправить заказ/лид в чат» в detail sheet.
- **Поиск по CRM** на вкладках Документы/Заказы/Задачи (`entity-chips` параллельно tab feed).
- **Недавно открывали** — `localStorage` через `mobileShellRecents.js`.

- Проверка: `npm run build`, `php artisan test --compact tests/Feature/MobileShellFeedTest.php tests/Feature/MobileDocumentUploadTest.php` (19 tests mobile-related).

### Ранее (phase 1–3)
- Shell, FCM, pull-to-refresh, share-to-chat, CRM link previews — commits `ca73ae2` … `6c9bd42`.

---

## Что сделано недавно (2026-07-04) — mobile CRM shell phase 3

- **Кнопка отправки в thread:** текст «Отпр.» заменён на иконку `Send` (квадратная кнопка 44×44, `aria-label="Отправить"`).
- **Preview CRM-ссылок в сообщениях:** `MobileCrmLinkPreview.vue` + расширенный `mobileMessageLinks.js` — заказ, документы заказа, лид, контрагент, задача; иконка типа, заголовок, подпись (без сырого URL в карточке).
- **Карточки вкладок:** общий `MobileShellEntityCard.vue` — badge типа сущности, кнопка share; документы показывают `order_id` вместо URL.
- **Upload из «Документы»:** после загрузки файла с вкладки (не из thread) открывается picker «Отправить в чат».
- **Phase 2 (ранее):** pull-to-refresh, share-to-chat, push deep links — commit `c697400`.
- **FCM / ntfy:** commit `ca73ae2` и ранее.
- Проверка: `npm run build`, `php artisan test --compact tests/Feature/MobileShellFeedTest.php tests/Feature/MobileEntityChipTest.php tests/Feature/MessengerTest.php`.

### Следующий шаг (phase 4)

- Mobile flow «Прикрепить файл» из thread уже есть (`MobileDocumentUploadWizard`); phase 4 — polish слотов, progress upload, тесты upload JSON.

---

## Что сделано недавно (2026-07-04) — mobile CRM shell phase 2

- **Pull-to-refresh** на всех вкладках mobile shell: composable `resources/js/composables/usePullToRefresh.js`, индикатор «Отпустите для обновления» на `main` списка в `Messenger.vue`; для `Чаты` — `reloadAll()` + коллеги, для остальных — `loadTab()`.
- **«Отправить в чат»** с карточек заказов, задач и документов: компонент `resources/js/Components/Mobile/MobileShareToChatPicker.vue`; кнопка Share на карточках → выбор диалога/коллеги → открытие thread с URL в composer.
- **Deep link из push:** `resources/js/support/mobilePushNavigation.js` — `orderId`, `highlightType`, парсинг `/orders/{id}` и `tab=documents`; в `Messenger.vue` — подсветка карточки (ring 4.5 с), scrollIntoView; foreground push обновляет активную вкладку (`crm-mobile-push-received`).
- **Ранее в этой ветке (phase 1 + FCM):** ntfy удалён, единый FCM через `MobilePushService`; per-colleague unread badges; отправитель в preview/аватар; guard FCM register при `FCM_ENABLED=false`. Commits: `861516b`, `3861461`, `ca73ae2`.
- **Prod FCM:** на сервере нужны реальные `FCM_PROJECT_ID` и путь `FCM_CREDENTIALS` к service account JSON; без них push с бэкенда не уйдёт (клиентский register работает при `google-services.json` в APK).
- Проверка: `npm run build`, `php artisan test --compact tests/Feature/MobilePushServiceTest.php tests/Feature/MessengerTest.php`.

### Следующий шаг (phase 3)

- Карточки сущностей богаче (preview как в desktop chips), upload wizard из вкладки «Документы», тесты JS для `mobilePushNavigation.js` при появлении vitest.

---

## Что сделано недавно (2026-07-04) — mobile CRM shell phase 1

- `resources/js/Pages/Mobile/Messenger.vue` перестроен в mobile shell с нижними вкладками `Чаты / Документы / Заказы / Задачи`; `Чаты` активны по умолчанию, thread открывается отдельным экраном без нижней панели.
- Вкладка `Чаты`: убран крупный заголовок `Автоальянс Чат` и общий текст про прочитанные сообщения; сверху компактный поиск, кнопка группы и маленькая кнопка обновления. Общий unread остался только badge на нижней вкладке `Чаты`, per-dialog unread остался в строках диалогов.
- Добавлен mobile UI создания группового чата поверх существующего `messenger.conversations.groups.store`; `resources/js/composables/useMessenger.js` получил `createGroup()`.
- `MessengerController::colleagues()` теперь отдаёт `phone` при наличии колонки `users.phone`; mobile список коллег показывает телефон, а рядом отдельную `tel:` кнопку звонка. Добавлен тест `test_colleagues_endpoint_includes_phone_for_mobile_contacts`.
- Вкладки `Документы`, `Заказы`, `Задачи` пока каркасные placeholders без AG Grid: это основа для следующей фазы карточек документов/заказов/задач.
- Проверка: пользователь вручную запустил `vendor/bin/pint --dirty --format agent`, `php artisan test --compact tests/Feature/MessengerTest.php`, `npm run build` — всё прошло. Во время работы у агента Shell-инструмент не возвращал exit status даже для `echo`, поэтому команды были подтверждены со стороны пользователя.

---

## Что сделано недавно (2026-07-04) — mobile CRM shell plan + prod status repair

- План mobile CRM обновлён в `docs/sync/mobile-crm-messenger-redesign-plan.md`: нижние вкладки `Чаты / Документы / Заказы / Задачи`; `Чаты` связывают сущности между собой, unread per-dialog как в Telegram, document chips переиспользуются в mobile без удаления из desktop CRM, файлы сначала сохраняются как CRM-документы/вложения сущности, затем в чат отправляется ссылка/карточка.
- Добавлена команда ручного audit-fix статусов: `php artisan crm:repair-status {lead|task} {id} {status} --reason=... [--user-id=...] [--dry-run]`.
- Команда задеплоена на prod точечно в `app/Console/Commands/RepairCrmStatusCommand.php`; `php artisan list` на prod показывает `crm:repair-status`.
- Prod диагностика: в `tasks` статуса `won` не было; `status='won'` был только в `leads` (5 записей), все на не-terminal этапах БП:
  - `10` → `negotiation`
  - `11` → `calculation`
  - `13`, `14`, `16` → `qualification`
- Исправление применено через `crm:repair-status`; финальная проверка prod: `won_leads=0`, `won_tasks=0`, `non_terminal_won_leads=0`.
- Локальная проверка: `vendor\bin\pint --dirty --format agent`, `php artisan test --compact tests\Feature\RepairCrmStatusCommandTest.php`.

---

## Что сделано недавно (2026-07-04) — Messenger UX redesign

- Добавлен общий клиентский composable `resources/js/composables/useMessenger.js`: загрузка диалогов/коллег, открытие direct-чата, загрузка thread, отправка сообщений, unread/error/loading state.
- `resources/js/Pages/Mobile/Messenger.vue` перестроен под mobile-native сценарий: экран списка диалогов и коллег → отдельный экран диалога с кнопкой назад, шапкой чата и закреплённым composer.
- `resources/js/Components/Layout/CrmCommandBar.vue`: web-панель мессенджера расширена до desktop split-view: слева поиск/список диалогов с preview/time/unread, справа выбранный чат с шапкой и сообщениями.
- `capacitor.config.json` снова стартует с `https://crm.avtoaliyans.ru/mobile/messenger`: guest попадает на `/mobile/login`, авторизованный пользователь сразу в чат, без редиректа в обычную CRM.
- Проверка: `php artisan route:list --path=messenger --except-vendor`, `php artisan route:list --path=mobile --except-vendor`, `npm run build`, `npm run cap:sync:android`, `android\gradlew.bat assembleDebug`.
- Prod: выложен свежий `public/build` на `/var/www/www-root/data/www/avtoaliyans.ru/public/build`; backup старой сборки `public/build.backup-20260704-1048`; права `www-root:www-root`, dirs `755`.
- Smoke: APK в эмуляторе после force-stop показывает новый mobile list-screen (`Автоальянс Чат`, поиск чатов и коллег, список коллег), без старого split-view.

### Checkpoint перед большой переделкой

- Текущая рабочая версия мессенджера фиксируется как точка возврата: mobile list → thread, CRM desktop split-view, общий `useMessenger`, APK стартует с `/mobile/messenger`.
- Git checkpoint: commit `Checkpoint mobile messenger UX` + tag `messenger-mobile-checkpoint-20260704` (создать в этой сессии).
- Откат к checkpoint в коде: `git checkout messenger-mobile-checkpoint-20260704` или cherry-pick/revert поверх текущей ветки по ситуации.
- Откат prod assets: на сервере `cd /var/www/www-root/data/www/avtoaliyans.ru && rm -rf public/build && cp -a public/build.backup-20260704-1048 public/build && chown -R www-root:www-root public/build && chmod -R 755 public/build`.
- Мелкие v2-идеи (телефоны, звонок, groups UI, document chips в mobile, убрать заголовок/refresh) не делать отдельными правками до проектирования новой мобильной CRM-оболочки.
- План большой переделки: `docs/sync/mobile-crm-messenger-redesign-plan.md`.

---

## Что сделано недавно (2026-07-04) — APK login и эмулятор

- Причина 404 в APK: `capacitor.config.json` открывал защищённый `/mobile/messenger`, а новый WebView без web-session/cookies попадал в auth redirect; отдельного mobile login route на проде ещё не было.
- Добавлен mobile login flow:
  - `GET /mobile/login` → `Mobile/Login.vue`.
  - `POST /mobile/login` → обычный Laravel session-auth, затем redirect intended `/mobile/messenger`.
  - гости на `/mobile/*` редиректятся на `/mobile/login`.
  - `capacitor.config.json` стартует с `https://crm.avtoaliyans.ru/mobile/login`.
- Проверка локально: `vendor\bin\pint --dirty --format agent`, `php artisan route:list --path=mobile --except-vendor`, `php artisan test --compact tests\Feature\Auth\AuthenticationTest.php`, `npm run cap:sync:android`, `android\gradlew.bat assembleDebug` — успешно.
- APK: `android/app/build/outputs/apk/debug/app-debug.apk`.
- Android SDK на этом ПК: `C:\AndroidSDK`; `JAVA_HOME=C:\Program Files\Android\Android Studio\jbr`; создан AVD `Avtoalyans_API_36`.

### Следующий шаг

- После push/deploy проверить `https://crm.avtoaliyans.ru/mobile/login` (должен быть 200), затем переустановить свежий APK на телефон.

---

## Что сделано недавно (2026-07-03) — мобильный контур CRM-мессенджера

- Текущий web-мессенджер найден: `MessengerController`, `MessengerService`, модели `Conversation` / `ChatMessage`, UI в `CrmCommandBar.vue`, таблицы `conversations`, `conversation_participants`, `chat_messages`.
- Добавлен первый mobile API слой: `routes/api.php` подключён в `bootstrap/app.php`; маршруты `/api/mobile/messenger/*` под `auth:sanctum` переиспользуют `MessengerController` (`conversations`, `messages`, `open`, `groups`, `read`, `unread-count`, `colleagues`, `document-chips`).
- При отправке сообщения `MessengerController::storeMessage()` вызывает `CabinetNotifier::notifyChatMessage()`: direct уведомляет второго участника, group — всех кроме автора, а при `recipient_user_id` только адресата.
- Push/ntfy: добавлен kind `chat_message` в `config/notifications.php` (`ntfy_kinds`), `CabinetInAppNotification` теперь смотрит `ntfy_kinds` с fallback на старый `approval_kinds`. Push несёт событие и payload (`conversation_id`, `message_id`, `author_id`), мобильное приложение должно дотягивать сообщения через API.
- Добавлен полноэкранный PWA-прототип `/mobile/messenger` (`resources/js/Pages/Mobile/Messenger.vue`): список диалогов, поиск коллег, открытие direct-чата, просмотр/отправка сообщений. Это web shell для проверки телефонного UX до упаковки в Capacitor.
- APK-обёртка Android: установлены `@capacitor/core`, `@capacitor/cli`, `@capacitor/android`; добавлен `capacitor.config.json` (`appId: ru.avtoaliyans.crm.messenger`, app name “Автоальянс Чат”, server URL `https://crm.avtoaliyans.ru/mobile/messenger`); сгенерирован проект `android/`.
- npm scripts: `cap:sync:android` (Vite build + Capacitor sync), `cap:open:android`, `apk:debug`. Debug APK собирается командой `npm run apk:debug`; собранный файл: `android/app/build/outputs/apk/debug/app-debug.apk`.
- Тесты: `tests/Feature/MessengerTest.php` расширен mobile Sanctum API сценарием и проверкой database-уведомления получателю.
- Проверка: `vendor/bin/pint --dirty --format agent`, `php -l` по изменённым PHP-файлам, `php artisan route:list --path=mobile/messenger --except-vendor`, `php artisan route:list --path=api/mobile/messenger --except-vendor`, `npm run build`, `npm run cap:sync:android`, `npm run apk:debug` — успешно. Android Studio SDK установлен в `C:\Users\tr0mb\AppData\Local\Android\Sdk`; JDK 21 поставлен через `winget` (`EclipseAdoptium.Temurin.21.JDK`), при необходимости явно выставить `JAVA_HOME=C:\Program Files\Eclipse Adoptium\jdk-21.0.11.10-hotspot`. `php artisan test --compact tests/Feature/MessengerTest.php` локально не доходит до assertions из-за `mysql` CLI не в `PATH`.

### Следующий шаг

- Поставить `android/app/build/outputs/apk/debug/app-debug.apk` на телефон и проверить логин CRM, список диалогов, отправку сообщений и внешний вид.
- Для команды собрать подписанный release APK; затем подключить native push для APK (Capacitor Push Notifications + FCM) и deep link/tap по `conversation_id`.

---

## Что сделано недавно (2026-07-03) — фикс ложного `won` у лидов

- Причина: `TaskStatus::leadStatusByTaskStatus()` маппил задачу `done` в статус лида `won`; `TaskController::syncLinkedLeadStatus()` вызывался при закрытии задач и мог пометить лид выигранным без движения по БП/воронке.
- Фикс: `app/Support/TaskStatus.php` теперь возвращает `null` для `done`, поэтому завершение задачи больше не меняет статус лида на `won`.
- Настоящий `won` остаётся через конвертацию лида в заказ (`LeadConversionService`) или терминальный этап бизнес-процесса с `terminal_outcome = won` (`LeadBusinessProcessService`).
- Data repair: миграция `2026_07_03_131346_repair_leads_won_on_lost_terminal_stage.php` исправляет только явные противоречия: лид `status = won`, но текущий этап БП терминальный `lost` → ставит `lost`.
- Тест: `tests/Feature/Feature/Leads/LeadTaskSyncTest.php` добавляет regression-сценарий — активный лид `qualification` остаётся `qualification` после закрытия связанной задачи.
- Проверка: `vendor/bin/pint --dirty --format agent`, `php -l app/Support/TaskStatus.php`, `php -l tests/Feature/Feature/Leads/LeadTaskSyncTest.php`, `php -l database/migrations/2026_07_03_131346_repair_leads_won_on_lost_terminal_stage.php`, `php artisan migrate --pretend --path=database/migrations/2026_07_03_131346_repair_leads_won_on_lost_terminal_stage.php`.
- Локально `php artisan test --compact tests/Feature/Feature/Leads/LeadTaskSyncTest.php` снова не доходит до assertions из-за окружения: `mysql` CLI не найден в `PATH` для `RefreshDatabase`.

---

## Что сделано недавно (2026-07-02) — MVP “Биржи грузов”

- Добавлен новый раздел CRM `/load-board` (“Биржа грузов”) для внутренней передачи грузов от продаж к закупке перевозчиков.
- Где искать: отдельный пункт левого меню **“Биржа грузов”** рядом с “Заказы”; прямой URL — `/load-board`.
- Backend: `LoadBoardController`, `LoadBoardPost`, `LoadBoardOffer`, request-валидация, маршруты `load-board.*`.
- БД: миграции `load_board_posts` и `load_board_offers` — груз, продавец, закупщик, статусы, экономика, требования и варианты перевозчиков.
- Доступ существующим ролям: миграция `2026_07_02_152142_grant_load_board_visibility_to_existing_roles.php` добавляет `load_board` текущим `admin/supervisor/manager/dispatcher`.
- UI: `resources/js/Pages/LoadBoard/Index.vue` — публикация груза, фильтры, “взять в работу”, “вернуть”, “добавить вариант”, “выбрать вариант”, “закрыть / без вариантов / отменить”.
- Workflow: добавлено ручное назначение закупщика прямо в карточке груза (`PATCH load-board/{post}/buyer`); назначение переводит груз в `in_work`, снятие возвращает в `new/has_offers`.
- ATI-слой: миграция `2026_07_03_092401_add_ati_dictionary_fields_to_load_board_posts.php` добавляет в `load_board_posts` справочные поля ATI (`cargo_type`, `pack_type`, `loading_type_items`, `truck_body_type_items`, `trailer_type_items`, габариты, ТН ВЭД, спецпризнаки, `ati_cargo_payload`). Форма “Выставить груз” использует `AtiDictionaryOptionCatalog` и `ati_dictionary_items`, а карточка груза показывает краткое ATI-резюме.
- ATI preview: `LoadBoardAtiReadinessService` + маршрут `POST load-board/{post}/ati/prepare` (`load-board.ati.prepare`) формируют готовность к ATI: `ready`, обязательные пропуски, рекомендации и payload для будущей внешней отправки. В карточке есть кнопка “Подготовить к ATI” и раскрываемый JSON preview.
- Реальную отправку на ATI пока не делаем: нет API-ключа. Оставлен только readiness/preview и сохранение справочников/payload для будущего шага.
- Задачи закупщику: `LoadBoardBuyerTaskService` создаёт обычную CRM-задачу для назначенного закупщика по грузам `high/urgent` при “взять в работу” или ручном назначении закупщика. Дубли не создаются: проверка по `tasks.meta->load_board_post_id` среди открытых задач.
- Acceptance workflow: миграция `2026_07_03_100513_add_acceptance_fields_to_load_board_posts.php` добавляет `accepted_offer_id`, `accepted_by`, `accepted_at`, `metadata`. Новый маршрут `POST load-board/{post}/offers/{offer}/approve` (`load-board.offers.approve`) принимает выбранный вариант (`selected` → `approved`), закрывает груз, завершает открытую задачу закупщика и фиксирует выбранного перевозчика в `orders.metadata.load_board_accepted_offer`. Если у заказа пустые `carrier_id/carrier_rate/carrier_payment_form`, они заполняются из выбранного offer.
- Список `/load-board` переведён на AG Grid: быстрый поиск, фильтры, сортировка, сохранение состояния колонок/фильтров, `GridViewsBar` с ключом `load_board`, контекстное меню строки. Детальная карточка выбранного груза осталась под таблицей и содержит старые workflow-действия, предложения перевозчиков и ATI preview.
- `GridViewCatalog` получил ключ `load_board`, чтобы представления “Биржи” сохранялись/закреплялись так же, как в заказах и контрагентах.
- Быстрые входы: из карточки лида кнопка “На биржу”; из грида заказов через ПКМ по строке — “Выставить на биржу грузов”. Оба перехода открывают `/load-board` с формой, предзаполненной из лида/заказа.
- UI лидов: кнопка “На биржу” в карточке лида выровнена по высоте с соседними кнопками; `LeadSalesCoachingPanel`/Outcome Intelligence уплотнён, строка “выиграно … из …” поднята к проценту Win rate.
- Доступ: новая область видимости `load_board`; добавлена в дефолты `manager`, `supervisor`, `dispatcher`, в сайдбар, избранное и мобильное меню.
- Тест: `tests/Feature/LoadBoardTest.php` покрывает happy path “продавец публикует → закупщик берёт → добавляет вариант → продавец выбирает”.

### Проверка

```powershell
vendor\bin\pint --dirty --format agent
php -l app\Http\Controllers\LoadBoardController.php
php -l app\Models\LoadBoardPost.php
php -l app\Services\LoadBoard\LoadBoardAtiReadinessService.php
php -l app\Services\LoadBoard\LoadBoardBuyerTaskService.php
php artisan route:list --name=load-board --except-vendor
php artisan migrate --pretend --path=database\migrations\2026_07_02_111644_create_load_board_posts_table.php --path=database\migrations\2026_07_02_111645_create_load_board_offers_table.php
php artisan migrate --pretend --path=database\migrations\2026_07_02_152142_grant_load_board_visibility_to_existing_roles.php
php artisan migrate --pretend --path=database\migrations\2026_07_03_092401_add_ati_dictionary_fields_to_load_board_posts.php
php artisan migrate --pretend --path=database\migrations\2026_07_03_100513_add_acceptance_fields_to_load_board_posts.php
php -l app\Support\GridViewCatalog.php
npm run build
```

- `ReadLints`: ошибок по новым/изменённым PHP/Vue файлам нет.
- `tests/Feature/LoadBoardTest.php` расширен проверками сохранения ATI id/label/items, `ati_cargo_payload`, `load-board.ati.prepare` preview, авто-задачи закупщику и acceptance workflow (`approved`, `closed`, запись в заказ, задача `done`); локально команда `php artisan test --compact tests\Feature\LoadBoardTest.php` всё ещё не доходит до assertions из-за старой проблемы окружения: `mysql` CLI не найден в `PATH` для `RefreshDatabase`.

### Следующий шаг

- После `php artisan migrate` проверить раздел в браузере на реальных ролях: `/load-board`, кнопка “На биржу” в лиде, ПКМ в заказах; отдельно проверить AG Grid на большом списке грузов.
- Следующий продуктовый шаг: после появления API-ключа ATI добавить реальную интеграцию “Отправить на ATI” поверх готового preview/payload.

---

## Что сделано недавно (2026-06-30) — UX тренажёра после smoke

- Тренажёр расширен с `max-w-6xl` до `max-w-[95rem]`, а центральная колонка диалога получила больше места в 3-колоночной сетке.
- В видимом UI скрыты технические `step_key/client_key` (`intro`, `next_step` и т.п.) в левом телесуфлёре, текущем шаге и связанных материалах.
- Зеленые/скруглённые акценты в ключевых контролах тренажёра заменены на синие/нейтральные прямоугольные состояния: телесуфлёр, “Вставить в сообщение”, “Завершить”, “Успешно”, `Плюс`.
- `TrainerRubricService`: пока менеджер не отправил первую реплику, критерии рубрики остаются `pending`; чек-лист больше не ставит галочки авансом из-за стартовых переходов графа.
- Проверка: `vendor\bin\pint --dirty --format agent`, `php artisan test --compact tests\Unit\TrainerRubricServiceTest.php tests\Feature\SalesScripts\TrainerGraphAdvanceTest.php`, `npm run build`.
- Ручной smoke: `/scripts/sessions/44` — `next_step/intro` в тексте страницы не видны, поля “Что зафиксировать” отображаются, рубрика показывает “ещё не проверено” для pending.

---

## Что сделано недавно (2026-06-30) — поля тренажёра и честная рубрика

- В тренажёре структурные поля текущего шага теперь показываются отдельным блоком “Что зафиксировать” даже на `Branch`-узлах (`capture_fields` в `play_presentation`).
- `trainerMessage` принимает `field_values`, сохраняет их через `SalesScriptPlaySessionService::saveFieldValues()` до продвижения графа и возвращает свежую `trainer_rubric`.
- После каждого ответа/перехода и после оценки реплики правая рубрика обновляется без перезагрузки.
- Убрана путаница `0/4 · 25%`: если есть pending-критерии, UI показывает “ещё не проверено”, а процент выводит только когда критерии уже не pending.
- Проверка: `php artisan test --compact tests\Feature\SalesScripts\TrainerGraphAdvanceTest.php tests\Unit\TrainerRubricServiceTest.php`, `npm run build`.

---

## Что сделано недавно (2026-06-30) — живая рубрика тренажёра

### Итог

- `TrainerRubricService` теперь не только выбирает тип рубрики (`discovery`, `price`, `documents`, `conflict`, `upsell`), но и считает `evaluated_criteria`, `passed_count`, `total_count`, `rubric_score`.
- Автооценка критериев опирается на надёжные сигналы: заполненные capture fields, посещённые узлы графа, исход сессии, `peer_reaction` и структурные `feedback_tags`.
- `TrainerScoreCalculator`: итоговый `trainer_score` получил небольшой рубричный сдвиг от `rubric_score`; исход сделки остаётся главным, но чек-лист теперь реально влияет на оценку.
- `SalesScripts/Play.vue`: правая панель показывает живые статусы критериев: `✓` выполнено, `…` ещё не ясно, `!` не выполнено после завершения. Под каждым пунктом показывается evidence — какие данные проверяются.
- Старое поле `criteria` сохранено для совместимости; новый UI использует `evaluated_criteria`, если оно есть.

### Проверка

```powershell
vendor\bin\pint --dirty --format agent
php artisan test --compact tests\Unit\TrainerRubricServiceTest.php tests\Feature\SalesScripts\TrainerGraphAdvanceTest.php
npm run build
```

- Ручной smoke: `/scripts/sessions/44` открывается, справа в рубрике видны смешанные статусы `✓`/`…` и пояснения evidence вместо одинаковых статичных галочек.

### Следующий шаг

- После накопления данных можно усилить критерии, которые сейчас проверяются приблизительно (`не назвал ставку без вводных`, `разложил ставку`) через анализ node-specific feedback и/или отдельные события по репликам менеджера.

---

## Что сделано недавно (2026-06-30) — самообучение тренажёра по оценкам

### Итог

- Добавлена миграция `2026_06_30_210504_add_feedback_context_to_sales_script_trainer_messages_table`: `sales_script_node_id`, `step_key`, `feedback_tags` для `sales_script_trainer_messages`.
- `SalesScriptController::trainerMessage`: реплики менеджера и ассистента теперь сохраняют привязку к текущему узлу/step_key; `trainerChatPayload` возвращает эти поля и теги.
- `UpdateTrainerMessagePeerReactionRequest`: оценка реплики принимает до 5 структурных тегов (`bad_wrong_stage`, `bad_missed_objection`, `useful_next_step` и т.п.).
- `SalesScripts/Play.vue`: после оценки реплики появляются быстрые кнопки причины (“слишком общо”, “не тот этап”, “мимо возражения”, “ясный следующий шаг”…).
- `TrainerFeedbackDigestService`: digest теперь строит `node_hotspots` и `feedback_tag_hotspots`, показывает конкретные шаги сценария, где копятся негатив/теги, и использует это в рекомендациях редактору.
- `SalesAssistant/TrainerAnalytics.vue`: в блок “Что улучшить в сценариях” добавлены “Шаги, которые надо переписать” и “Причины оценок”.

### Проверка

```powershell
vendor\bin\pint --dirty --format agent
php artisan test --compact tests\Unit\TrainerFeedbackDigestServiceTest.php tests\Feature\SalesScripts\TrainerGraphAdvanceTest.php
npm run build
php artisan migrate --no-interaction
```

- Ручной smoke: `/sales-assistant/trainer/analytics` открывается, новый текст ограничений отображается.
- Ручной smoke: `/scripts/sessions/44` — после клика `Минус` появляются причины оценки; тестовая оценка затем снята.

### Следующий шаг

- После накопления новых оценок проверить `node_hotspots`: если данных достаточно, добавить переход из аналитики сразу в редактор конкретного узла сценария.

---

## Что сделано недавно (2026-06-30) — тексты и обучение тренажёра

### Итог

- Реализован план `trainer-content-feedback` без изменения plan-файла.
- `SalesScriptsDemoSeeder`: `Branch`-узлы всех активных демо-сценариев переписаны в формат текущего хода разговора: “Текущий ход”, один фокусный вопрос, поля для фиксации, без служебного “Выберите реакцию”.
- `SalesAssistant/Trainer.vue`: профили покупателей уточнены — добавлены условия, при которых клиент соглашается двигаться дальше, и реакции на шумные/общие ответы менеджера.
- Добавлен `TrainerFeedbackDigestService`: агрегирует trainer feedback по сценариям/профилям, негативные `peer_reaction/auto_peer_reaction`, `stuck/failure`, live-возражения и незаполненные capture fields.
- `SalesAssistantController::trainerAnalytics`: отдаёт новый проп `feedback_digest`.
- `TrainerAnalytics.vue`: добавлен блок “Что улучшить в сценариях” с рекомендациями редактору, проблемными сценариями/профилями, живыми возражениями, незаполненными полями и ограничениями текущего контура.
- Важно: автопереписывания сценариев нет; рекомендации human-in-the-loop. Узловая привязка негативных реплик пока ограничена, потому что `sales_script_trainer_messages` не хранит `node_id`.

### Проверка

```powershell
vendor\bin\pint --dirty --format agent
php artisan test --compact tests\Feature\SalesScripts\SalesScriptFlowTest.php tests\Unit\TrainerFeedbackDigestServiceTest.php tests\Unit\TrainerCoachingInsightsServiceTest.php
npm run build
```

- Ручная проверка в браузере: `/sales-assistant/trainer/analytics` открывается, блок “Что улучшить в сценариях” отображает рекомендации и ограничения.

### Следующий шаг

- Если нужен более точный контур “что помогло / не помогло” по узлам, следующая миграция должна сохранять `sales_script_node_id` или `step_key` на `sales_script_trainer_messages` в момент каждой реплики ассистента.

---

## Что сделано недавно (2026-06-30) — Тренажёр Без Шума

### Итог

- Реализован план `trainer-ui-guidance` без изменения plan-файла.
- `TrainerGraphCoordinatorService`: `Ask`/`Branch` больше не перескакивают по линейному переходу сразу после реплики менеджера; `Ask` идёт дальше только после ответа клиента, если нет матча реакции.
- `TrainerClientReactionMatcher`: усилены ключи для скептика к смене перевозчика (`перевозчик устраивает`, `нас устраивает`, `работает штатно`, `напишите на почту`, `посмотрю`, `предложение`).
- `TrainerDialogHintService`: “связанные материалы” ограничены ближайшими 1-2 переходами и не вытягивают дальние будущие блоки; в ответ добавляется `why`.
- `TrainerScenarioGuidanceService`: технический линейный “Далее” больше не попадает в “Реакции клиента на шаге”.
- `SalesScripts/Play.vue`: тренажёр разложен в три зоны — слева телесуфлёр “что делать сейчас” с кнопкой вставки, центр диалога, справа “Прогресс и качество” с чек-листом рубрики, прогнозом реакций и свернутыми связанными материалами.

### Проверка

```powershell
vendor\bin\pint --dirty --format agent
php artisan test --compact tests/Feature/SalesScripts/TrainerGraphAdvanceTest.php tests/Unit/TrainerDialogHintServiceTest.php tests/Unit/TrainerScenarioGuidanceServiceTest.php
npm run build
```

- Ручная проверка в браузере: профиль `ЛПР: скептик к смене перевозчика`, сценарий `Холодный звонок`.
- После первой реплики сценарий перешёл в `clarify_contact`; справа нет преждевременных подсказок про пилот/процедуру входа.
- На `Ask` шаге справа показывается понятный текст ожидания свободного ответа вместо обрезанного `Далее: ...`.
- После ответа клиента граф перешёл к `next_step` только после клиентской реплики.

### Следующий шаг

- Если UI устроит визуально, можно деплоить обычной фронтенд-сборкой; при необходимости отдельно доработать стиль чекбоксов рубрики под реальные сохранённые критерии.

---

## Что сделано недавно (2026-06-30) — UI тренажёра и контакты витрины

### Итог

- Коммит `9bdad83` запушен на `origin/master`: CRM actions после Play/Trainer, рубрики тренажёра, artifact preview, cleanup roadmap, кликабельные контакты витрины.
- `resources/js/Components/Public/PublicSiteShell.vue`: городской телефон `+7 8482 55 99 99` и email витрины сделаны кликабельными (`tel:` / `mailto:`) в контактах и футере.
- Следующая правка после `9bdad83`: оптимизация `resources/js/Pages/SalesScripts/Play.vue` по скроллам и подсказкам тренажёра.
- В Play тренажёра убран лишний внутренний scroll страницы: тренажёр больше не задаёт `overflow-y-auto` на root, чтобы не “сливались” вертикальные скроллы.
- Правая колонка подсказок стала отдельной sticky-карточкой с внутренним scroll, отступом от края и `scrollbar-gutter: stable`.
- Чат поднят сразу под заголовок “Диалог”; кнопка завершения компактно перенесена в шапку диалога.
- Оценка тренировки и доп. указания ассистенту убраны из середины диалога в сворачиваемый блок “Настройки и оценка тренировки” под полем ввода.
- Рубрика оценки перенесена в правую панель подсказок.
- Подсказки справа разведены по смыслу:
  - “Реакции клиента на шаге” — возможные ответы текущей ветки.
  - “Дополнительно по теме диалога” — лексические совпадения, только если клиент уже затронул тему; не обязательный следующий шаг.
- Дубль “Текущий шаг сценария” скрывается, если та же инструкция уже показана в основном телесуфлёре “Скажите клиенту”.

### Проверка

```powershell
ReadLints resources/js/Pages/SalesScripts/Play.vue   # ошибок нет
npm run build                                       # успешно
```

### Следующий шаг

- Визуально открыть тренажёр в браузере на сценарии с подсказками `procedure_probe` и проверить, что наружный скролл страницы и внутренний скролл панели подсказок ощущаются раздельно.
- После `git pull` проверить тренажёр визуально; если всё устраивает — задеплоить фронтенд-сборку обычным способом.

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
