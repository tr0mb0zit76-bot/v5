# Биржа грузов и закупка перевозки (архитектура)

Техническая карта модуля **Биржа грузов** (`/load-board`) и эволюции к платформе закупок **B + advisory AI (C)**.

**Последнее обновление:** 2026-07-08

---

## Цель

Внутренняя CRM-биржа для передачи груза от **продаж** к **закупке перевозчиков**: публикация кейса, сбор вариантов, согласование с продавцом, фиксация в заказе и накопление статистики ставок по коридорам.

Внешний ATI на первом этапе — **ручной** (`ati_manual`); API подключается после появления ключа.

---

## Роли и сущности

| Роль / поле | Смысл сейчас | Перспектива |
| --- | --- | --- |
| `seller_id` | Менеджер продаж, выставил груз | `order_owner_id` — владелец сделки |
| `buyer_id` | Закупщик, ведёт поиск перевозчика | отдельная роль закупки |
| — | — | `dispatcher_id` — диспетчер исполнения |
| — | — | `buying_own_company_id` — экспедиторское юрлицо закупки |
| `LoadBoardPost` | Публикация груза на бирже | эволюция → `ProcurementCase` |
| `LoadBoardOffer` | Вариант перевозчика + ставка | кандидат в пул ATI / internal |

---

## Жизненный цикл (`load_board_posts.status`)

```
new → in_work → has_offers → seller_review → closed
                    ↘ no_options / cancelled
```

| Статус | Когда |
| --- | --- |
| `new` | Опубликован, закупщик не назначен |
| `in_work` | Закупщик взял или назначен |
| `has_offers` | Есть хотя бы один оффер |
| `seller_review` | Продавец выбрал вариант для согласования |
| `closed` | Оффер принят (`approved`) |
| `no_options` / `cancelled` | Закрыт без победителя |

Вкладки грида: **Активные**, **Мои продажи**, **Моя закупка**, **Есть офферы**, **Закрытые**, **Все**.

---

## Модель данных

### `load_board_posts`

Маршрут, груз, экономика (`customer_rate`, `target_carrier_rate`), ATI-справочники (`ati_cargo_payload`, словари кузова/упаковки), связи `lead_id` / `order_id` / `customer_id`, acceptance (`accepted_offer_id`, `accepted_by`, `accepted_at`, `metadata`).

### `load_board_offers`

| Поле | Назначение |
| --- | --- |
| `carrier_id`, `carrier_rate` | Перевозчик и ставка |
| `status` | `proposed` → `selected` → `approved` / `rejected` |
| `source` | `internal_crm`, `ati_manual`, `phone`, `email`, `messenger` |
| `payment_form`, `conditions`, … | Условия оффера |

### `load_board_rate_observations`

Наблюдения для аналитики коридоров (не дублирует оффер, а снимок для статистики):

- `corridor_key` — хеш маршрута + кузов + весовой bucket (`LoadBoardCorridorKey`)
- `margin_abs`, `margin_pct` — от `customer_rate` поста
- `outcome` — `open`, `approved`, `not_selected`, `rejected`, `expired`

Миграция: `2026_07_08_181259_create_load_board_rate_observations_table.php`.

---

## Backend

| Компонент | Файл |
| --- | --- |
| CRUD + workflow | `app/Http/Controllers/LoadBoardController.php` |
| Список + пагинация | `app/Services/LoadBoard/LoadBoardPostIndexService.php` |
| DTO для Inertia/JSON | `app/Services/LoadBoard/LoadBoardPostPresenter.php` |
| ATI readiness | `app/Services/LoadBoard/LoadBoardAtiReadinessService.php` |
| Задачи закупщику | `app/Services/LoadBoard/LoadBoardBuyerTaskService.php` |
| Статистика ставок | `app/Services/LoadBoard/LoadBoardRateObservationService.php` |

### Маршруты (`load-board.*`)

- `GET /load-board` — Inertia, первая страница грида (50 строк)
- `GET /load-board/rows` — JSON для infinite scroll
- `GET /load-board/{post}/insights` — коридорная статистика
- `POST …/offers`, `…/select`, `…/approve` — workflow офферов
- `POST …/ati/prepare` — preview payload ATI

### Принятие оффера → заказ

`approveOffer` в транзакции:

1. Оффер `approved`, остальные `rejected`
2. Пост `closed`, `accepted_offer_id`
3. `orders.metadata.load_board_accepted_offer`
4. Если пусто: `carrier_id`; ставка — в `orders.carrier_rate` **или** `financial_terms.contractors_costs` (если колонки на `orders` нет)
5. Задача закупщика → `done`

---

## Frontend

| UI | Файл |
| --- | --- |
| Грид + вкладки + infinite scroll | `resources/js/Pages/LoadBoard/Index.vue` |
| Карточка кейса (Обзор / Офферы / ATI) | `resources/js/Components/LoadBoard/LoadBoardPostCard.vue` |
| Infinite scroll composable | `resources/js/composables/useAgGridInfiniteScroll.js` |

Грид: колонки **лучшая ставка**, **маржа (лучш.)**, **источники**, **маржа (выбр.)** — из `offers_summary` и полей офферов.

Сайдбар: иконка **Gavel**, область `load_board`.

---

## Статистика и AI (фаза C)

1. Каждый оффер → `load_board_rate_observations` (`recordOfferCreated`)
2. Исходы: `approved`, `not_selected`, `rejected`, `expired`
3. `corridorInsightsForPost` — min/avg/max ставки и маржи по коридору (до 120 точек)
4. Карточка кейса, вкладка «Офферы» — запрос `load-board.insights` для подсказки закупщику

Полноценный AI-советник (ранжирование офферов, риск срыва) — следующая фаза поверх накопленных наблюдений.

---

## ATI

Сейчас:

- Справочники из `ati_dictionary_items` + `AtiDictionaryOptionCatalog`
- `LoadBoardAtiReadinessService::preview()` — `ready`, `missing`, `warnings`, `payload`
- Источник оффера `ati_manual` при ручном вводе с ATI

Позже:

- Публикация/синхронизация через API при наличии ключа
- Связь `ati_load_id` на посте / кейсе

---

## Доступ

- Область видимости: `load_board` (`RoleAccess`, middleware `visibility.area:load_board`)
- Saved views: ключ грида `load_board` в `GridViewCatalog`

---

## Тесты

`tests/Feature/LoadBoardTest.php`:

- полный workflow продавец → закупщик → approve
- пагинация / `rows`
- observation при создании оффера
- `insights` endpoint

---

## Дорожная карта

| Фаза | Содержание |
| --- | --- |
| **Сделано (2026-07)** | Грид + вкладки, infinite scroll, карточка с офферами/ATI, rate observations, insights API |
| **Ближайшее** | `dispatcher_id`, split мастера заказа owner/dispatcher, `metadata.compensation_split` |
| **Средний срок** | `ProcurementCase` как обёртка над постом + связь с несколькими заказами/лидами |
| **ATI API** | Автопубликация после ключа |
| **Пул перевозчиков** | Internal CRM → внешние источники, единый реестр кандидатов |

---

## Деплой

```powershell
php artisan migrate
npm run build
php artisan test --compact tests/Feature/LoadBoardTest.php
```

Миграция observations обязательна для статистики и поля `source` на офферах.
