# ТЗ: внешние пользователи контрагента (Traklo + мессенджер + портал)

> **Статус:** фаза D (customer web portal + «Написать в Traklo») — реализовано.  
> **Обновлено:** 2026-07-05  
> **Связанные документы:** `docs/sync/v5-local-Components-Documents-Registry.md`, handoff Traklo, портал `/portal/carrier/{token}`.

---

## 1. Цель

Дать **контакту контрагента** (перевозчик / заказчик) легальный вход в экосистему CRM:

- **Traklo** — мобильный клиент: свои заказы, документы, чат с менеджером;
- **Мессенджер** — сквозное общение (текст, ссылки, system-сообщения);
- **Без доступа** к внутренним разделам CRM (лиды, финансы, настройки, чужие заказы, каталог сотрудников).

Внутренние сотрудники продолжают работать в CRM + Traklo как сейчас; контрагент видит **отдельный shell**, не desktop wizard.

---

## 2. Не делаем в первой фазе

- Полноценный «кабинет контрагента» на desktop Inertia (все разделы CRM).
- Отдельное приложение / второй APK.
- Самостоятельную регистрацию контрагента без приглашения менеджера.
- MCP, Command bar, AI, mail:sync, Книгу продаж, тренажёр для external.
- Один контакт одновременно **и заказчик, и перевозчик** (два `external_party` на одном User) — отложено; MVP = простой «внешний пользователь» одной стороны.
- **Общий групповой чат** заказчик + перевозчик + staff на один заказ — **категорически запрещён** (см. §6.5).

---

## 3. Акторы

| Актор | Аутентификация | Клиент |
|-------|----------------|--------|
| **Staff** (менеджер, логист, …) | `User`, корп. роли | CRM desktop + Traklo |
| **Carrier contact** | `User` + роль перевозчика | Traklo (external shell) |
| **Customer contact** | `User` + роль заказчика | Traklo (external shell) |

Контакт **не** дублирует `Contractor` — это физическое лицо (`ContractorContact`), привязанное к одному `Contractor`.

**Один User = одна сторона:** либо `external_party=carrier`, либо `customer`, не оба. Если контакт «и там и там» — отдельная проработка позже, в MVP не делаем.

### 4.0. «Основной контакт в Traklo»

На `contractor_contacts` — флаг **`is_traklo_primary`** (один на контрагента, или один primary на party — уточнить при реализации UI).

- Именно он — default для invite, чата и уведомлений по этому `Contractor`.
- Если флаг не задан — кнопка «Пригласить в Traklo» предлагает выбрать контакт явно.
- Смена primary не ломает существующий User другого контакта.

---

## 4. Модель данных (предложение)

### 4.1. Расширение `users`

| Поле | Тип | Назначение |
|------|-----|------------|
| `is_external` | bool, default false | Быстрый gate «не staff» |
| `contractor_id` | FK nullable → `contractors` | Юрлицо контрагента |
| `contractor_contact_id` | FK nullable → `contractor_contacts` | Конкретный контакт |
| `external_party` | enum nullable: `carrier`, `customer` | Роль в сделках (может дублировать роль, но ускоряет scope) |

Ограничения:

- `is_external = true` ⇒ `contractor_id` обязателен;
- email User = email контакта (уникальность как сейчас);
- `mail_sync_enabled = false`, `can_management_accounting = false`, … — дефолты при создании.

### 4.2. Роли CRM

Две системные роли (seed):

| Роль | `external_party` | Области видимости (минимум) |
|------|------------------|----------------------------|
| **Контакт перевозчика** | carrier | `counterparty_orders`, `counterparty_documents`, *(messenger — см. §6)* |
| **Контакт заказчика** | customer | те же |

Опционально позже: `counterparty_messenger` как отдельная область; на MVP достаточно `is_external` + проверок в Messenger API.

### 4.3. Новые ключи `visibility_areas`

Добавить в `RoleAccess::visibilityAreaOptions()`:

| Ключ | Label | Смысл |
|------|-------|--------|
| `counterparty_orders` | Мои заказы | Список заказов по party-scope |
| `counterparty_documents` | Документы | Слоты upload/download по party |
| `counterparty_portal` | Данные перевозки | Форма ТС/водитель (carrier), read-only статус (customer) |

**Не выдавать** external: `leads`, `dashboard`, `finance_*`, `settings*`, `reports`, `load_board`, `mail`, …

### 4.4. Модель чатов (расширение `conversations`)

**Принцип:** чат привязан к **отношению staff ↔ контрагент (contractor_id + party)**, а не к одному заказу. Внутри чата «висят» все заказы этой стороны.

| Поле | Тип | Назначение |
|------|-----|------------|
| `channel` | enum: `internal`, `counterparty` | UI: «Команда» / «Контрагенты» |
| `contractor_id` | FK | Юрлицо контрагента |
| `external_party` | enum: `carrier`, `customer` | Сторона в сделке |
| `primary_staff_user_id` | FK nullable → users | Менеджер/логист «ведущий» диалог (default — из primary Traklo contact flow или закреплённый staff) |
| `context_type` | deprecated для counterparty | Для internal: `general`; **не** `order` как единственный ключ |

**Запрещено:** conversation, где одновременно участвуют external customer **и** external carrier по одному заказу (или любой «трёхсторонний» чат).

**По одному заказу — два независимых counterparty-чата:**

```text
Заказ MOB-42
  ├── чат staff ↔ заказчик (contractor_id = customer, все заказы заказчика в боковой панели / pin)
  └── чат staff ↔ перевозчик (contractor_id = carrier, все заказы перевозчика)
```

Сообщения могут ссылаться на конкретный заказ (`message.order_id`, link preview, system «документ к MOB-42»), но **thread один на контрагента**, не новый чат на каждый заказ.

Уникальность (MVP): `(channel=counterparty, contractor_id, external_party, primary_staff_user_id)` — один активный thread; при смене менеджера — опционально новый thread или reassign (фаза 2).

---

## 5. Доступ к заказам (party-scope)

Отдельный резолвер **`CounterpartyOrderAccess`** (не путать с `manager_id` scope).

### 5.1. Заказчик (`external_party = customer`)

Заказ виден, если:

```text
orders.customer_id = users.contractor_id
AND заказ не удалён / не архивный (как у staff)
```

### 5.2. Перевозчик (`external_party = carrier`)

Заказ виден, если **хотя бы одно**:

- в `orders.performers[]` есть `contractor_id = users.contractor_id`;
- в `contractors_costs` / leg costs (если используется) — тот же contractor;
- есть активный `order_portal_invites` для пары `(order_id, contractor_id)`.

### 5.3. Документы

- Слоты из `OrderDocumentRequirementSlotBuilder` фильтровать по `metadata.party`:
  - carrier → `party = carrier` (ТТН, waybill, …);
  - customer → `party = customer` (заявка CP, **packing list / invoice**, УПД, акт — по регламенту слотов).
- **Заказчик на MVP может upload**, не только download: packing list, invoice и прочие customer-слоты, если они есть в чек-листе заказа.
- Upload/download — общий сервис (как `OrderCarrierPortalDocumentService`), автор = `User` (external) или token portal; audit в `order_documents.metadata`.
- В карточке заказа (staff) и в external UI — единый список документов по party; system message в чат при upload.

### 5.4. Поля заказа «что видит контрагент»

**Whitelist** (read-only на MVP):

- номер заказа, маршрут (адреса, даты план/факт по точкам), тип груза (без внутренней маржи);
- контакт менеджера (имя, телефон);
- статус перевозки (упрощённый, не внутренний BP);
- документы по своим слотам.

**Скрыть:** ставки заказчик/перевозчик другой стороны, KPI, внутренние задачи, финансы, график оплат (кроме явно разрешённого customer-пакета позже), другие контрагенты на заказе.

---

## 6. Мессенджер: контакты в чатах без утечки CRM

### 6.1. Проблема сейчас

- `GET messenger/colleagues` — **все** активные `users` (кроме cursor).
- External User попадёт в каталог всей компании и увидит всех сотрудников.

### 6.2. Правила MVP

| Кто | Кого может начать / видеть |
|-----|----------------------------|
| **Staff** | Все staff; external — **только** если есть общий `conversation` или контрагент участвует в **заказе staff** (manager/responsible) |
| **External** | **Только** участники своих conversations; **нет** полного списка staff |

Реализация (концепт):

- `colleagues` для staff — без изменений или с фильтром `is_external` optional;
- `colleagues` для external — **пусто** или endpoint `counterparty/contacts` = участники существующих чатов;
- **Создание direct staff → external** — только из карточки заказа / контрагента («Написать в Traklo»), не из глобального списка;
- **External → staff** — только ответ в существующем thread или «Написать менеджеру» (staff из `primary_staff_user_id` или ответственный по **основному контакту Traklo** / заказу).

### 6.5. Изоляция сторон (обязательное правило)

| Правило | Деталь |
|---------|--------|
| **Нет общего чата** | Заказчик и перевозчик **никогда** не в одном conversation |
| **Два чата на заказ** | Отдельный thread с customer-contractor и отдельный с carrier-contractor |
| **Один thread на контрагента** | Все заказы этой стороны доступны в UI чата (список / pin / фильтр), не отдельный чат на MOB-xx |
| Staff видит оба | Во вкладке «Контрагенты» — две строки, если на заказе две стороны |

### 6.3. Каналы в UI Traklo

Вкладки в списке чатов:

1. **Все** (default для external — только counterparty).
2. **Команда** — `channel=internal`, только staff.
3. **Контрагенты** — `channel=counterparty`.

Staff по умолчанию видит оба; external — только counterparty (+ скрытая вкладка «Команда» отсутствует).

### 6.4. Типы сообщений

| `message_type` | Пример | CRM side-effect |
|----------------|--------|-----------------|
| `text` | «Можем в субботу» | — |
| `link` | preview заказа / документа | — |
| `system` | «Перевозчик загрузил ТТН к MOB-42» | + запись в timeline заказа |
| `structured` *(фаза 2)* | согласование даты/ставки | + PATCH поля / задача |

---

## 7. Traklo shell для external

### 7.1. Routing после login

```text
auth user.is_external ?
  → /mobile/messenger (counterparty mode, ограниченный nav)
  : → текущий shell (staff)
```

### 7.2. Bottom nav (external)

| Ключ | Экран |
|------|--------|
| `counterparty_orders` | Список «Мои перевозки» |
| `counterparty_documents` | Документы по активным заказам |
| `chats` | Мессенджер (counterparty channel) |
| *(carrier)* | «Данные рейса» (бывш. portal fleet form) |

Не показывать: leads, tasks (internal), dashboard компании, documents registry целиком.

### 7.3. Staff Traklo

Без изменений + action «Пригласить в Traklo» / «Открыть чат с контактом» из карточки заказа.

---

## 8. Защита CRM от лишнего (fail closed)

### 8.1. Middleware-стратегия

1. **`RejectExternalFromInternalRoutes`** — если `user.is_external`, запрет всех маршрутов кроме whitelist:
   - `mobile/messenger`, `mobile/shell/counterparty/*`, `messenger/*`, `logout`, `profile` (минимальный), portal API.
2. **`EnsureVisibilityAreaAccess`** — уже есть; для external ролей только counterparty-areas.
3. Inertia shared props — не отдавать `visibility_areas` staff-меню; отдельный `external_nav`.

### 8.2. Whitelist маршрутов (MVP)

```text
GET  /mobile/messenger
GET  /mobile/shell/counterparty/*
POST /messenger/*
GET  /portal/* (legacy token, переходный период)
PATCH /profile/password (optional)
POST /logout
```

Всё остальное (`/orders`, `/leads`, `/finance`, …) → **403** или redirect на mobile messenger.

### 8.3. API / Sanctum

- External tokens **не** выдают scope MCP.
- Mobile shell endpoints проверяют `CounterpartyOrderAccess`, не `manager_id`.

---

## 9. Выдача доступа (UX для staff)

### 9.1. Точка входа

`ContractorContact` (карточка контрагента → контакт):

- Флаг **«Основной контакт в Traklo»** (`is_traklo_primary`);
- Кнопка **«Доступ в Traklo»** (для primary или явный выбор контакта);
- **Invite по ссылке** (не автоматический SMS на MVP): одноразовая / сроковая ссылка «установить пароль» + опционально QR;
- В тексте invite — ссылка на **скачивание Traklo** с витрины (§17).

### 9.2. Сценарии

| Сценарий | Действие |
|----------|----------|
| Primary contact, User нет | Создать `User` (external), роль по party контрагента, отправить invite-link |
| User уже есть | Повторный invite = reset password link |
| Email занят staff | Ошибка |
| Не primary | Предупредить; разрешить invite с подтверждением |
| Увольнение / смена primary | `is_active=false` на User; флаг primary на другом контакте |

### 9.3. Открытие чата (не «чат на заказ»)

При первом обращении к контрагенту по заказу:

- найти или создать conversation `{ channel: counterparty, contractor_id, external_party, primary_staff_user_id }`;
- participants: staff + external User (primary или явно выбранный);
- в UI чата — панель **«Заказы контрагента»** с pin на текущий `order_id`;
- system message: «Обсуждаем заказ MOB-42» (не «создан новый чат»).

Смена заказа в том же thread — новые сообщения с `order_id`, без нового conversation.

---

## 10. Web-портал (token) — остаётся навсегда

| Канал | Когда |
|-------|--------|
| **Traklo + User** | Основной для постоянных контактов |
| **Web** `/portal/carrier/{token}`, позже `/portal/customer/{token}` | Кому лень ставить приложение; разовые / новые перевозчики |

- Token-портал **не снимаем** с релиза external User.
- Один backend слотов документов; два входа (session User vs token).
- На landing портала — ссылка «Скачать Traklo» и «Войти, если уже есть доступ».

Опционально позже: token → «привязать к аккаунту» после set password.

---

## 11. Матрица «кто что видит»

| Ресурс | Staff | Carrier external | Customer external |
|--------|-------|------------------|-------------------|
| Все заказы CRM | scope role | только carrier-party | только customer-party |
| Маржа / KPI | ✓ (role) | ✗ | ✗ |
| Чужие контрагенты | ✓ | ✗ | ✗ |
| Каталог colleagues | ✓ | ✗ | ограниченный |
| Upload ТТН / waybill | ✓ | ✓ (слоты carrier) | ✗ |
| Upload packing / invoice | ✓ | ✗ | ✓ (customer-слоты) |
| Download CP / UPD | ✓ | ✗ | ✓ |
| Leads / pipeline | ✓ | ✗ | ✗ |
| Настройки / users | admin | ✗ | ✗ |

---

## 12. Фазы реализации

### Фаза A — документация (параллельно)

- **Runbook** (dev): архитектура external User, деплoy, без APK для контрагента.
- **Инструкция** (Книга продаж): как выдать доступ, как писать контрагенту, без техн. деталей.

### Фаза B — MVP external User (backend)

1. Миграция: `users` + `contractor_contacts.is_traklo_primary`;
2. Роли seed + counterparty areas;
3. `CounterpartyOrderAccess` + counterparty mobile shell API;
4. Middleware whitelist;
5. Provision UI + **invite link** на `ContractorContact`.

### Фаза C — Messenger

1. `conversations` по модели §4.4 (contractor + party, не order-only);
2. Запрет смешанных участников; панель заказов в чате;
3. Фильтр `colleagues`;
4. UI «Контрагенты» в Traklo; system messages → timeline.

### Фаза D — Customer web portal + витрина

1. `/portal/customer/{token}` (или auth customer User);
2. Customer upload packing/invoice;
3. **Ссылка «Скачать Traklo»** на публичной витрине (`PublicSiteShell`, `/transport-request`).

---

## 13. Риски и mitigations

| Риск | Mitigation |
|------|------------|
| External открыл `/orders` по URL | Middleware fail closed §8 |
| Утечка через colleagues | §6.2 — не отдавать полный каталог |
| Два User на один contact | Unique `contractor_contact_id` или audit |
| mail:sync на external | Defaults off; guard в UserManagement |
| Контрагент видит второго перевозчика на заказе | Whitelist полей; отдельные чаты; no group |
| Случайный group chat customer+carrier | Валидация participants при create/add |

---

## 14. Согласованные решения (2026-07-05)

| # | Вопрос | Решение |
|---|--------|---------|
| 1 | Один contact — carrier и customer? | **Нет в MVP.** Сначала простой external User одной стороны. |
| 2 | Default собеседник / контакт | **«Основной контакт в Traklo»** в карточке (`is_traklo_primary`). |
| 3 | Customer upload | **Да:** packing list, invoice и др. customer-слоты. |
| 4 | Invite | **Ссылка** (менеджер копирует / шлёт). **APK на витрине** (§17). SMS — не MVP. |
| 5 | Групповой чат | **Запрещён** customer+carrier вместе. Два чата на заказ; **один thread на контрагента** со всеми его заказами. |
| 6 | Web portal | **Остаётся** параллельно Traklo. |

---

## 15. Критерии приёмки MVP (фаза B+C)

- [ ] На контакте можно отметить **основной контакт в Traklo**.
- [ ] Менеджер выдаёт **invite-link** → external User (carrier или customer).
- [ ] Контакт логинится в Traklo, видит **только** свои заказы (party-scope).
- [ ] Контакт **не** открывает `/leads`, `/finance`, desktop CRM (403).
- [ ] **Два** counterparty-чата на заказ (customer / carrier), **без** общего.
- [ ] **Один** thread на контрагента; в чате видны **все** его заказы.
- [ ] Carrier upload + customer upload (packing/invoice) по слотам.
- [ ] Web portal token по-прежнему работает.
- [ ] Staff: вкладка «Контрагенты»; external не видит каталог staff.
- [ ] Деактивация User блокирует login и upload.

---

## 16. Связь с Traklo-инструкцией (пользователь vs разработчик)

| Документ | Аудитория | Содержание |
|----------|-----------|------------|
| `docs/traklo-runbook.md` *(будущий)* | разработчик | external User, middleware, API, деплoy, APK |
| Книга продаж «Traklo для менеджера» | пользователь | primary contact, invite-link, два чата на заказ, **без APK-сборки** |
| Книга продаж «Traklo для контрагента» *(опционально)* | external | ссылка с витрины, вход, документы, web vs app |

---

## 17. Витрина: скачивание Traklo (смежная задача)

- На **публичной витрине** (`PublicSiteShell`, footer, `/transport-request`): «Скачать Traklo» → `/downloads/traklo.apk` или route `mobile.app-update`.
- Тот же URL в invite-link и в инструкции для менеджера.
- Runbook: когда bump APK vs достаточно web.

---

*Следующий шаг:* фаза A (инструкция + runbook) или оценка фазы B.
