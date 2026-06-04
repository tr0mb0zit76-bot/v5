# Портрет контрагента — MVP (спецификация)

Связано с [commercial-intelligence-roadmap.md](./commercial-intelligence-roadmap.md) (блок **P0**).  
Цель: дать менеджеру **инструмент фиксации** и ассистенту — **структурированный контекст** для подсказок; не заменяет почту и HITL-извлечение, но работает уже без них.

---

## Принципы

| Принцип | Решение |
|--------|---------|
| Портрет ≠ свободная заметка | Типизированные слоты + опциональный текст |
| Каждый важный факт | `source_type` + `source_id` (interaction, lead, mail, manual) |
| AI не пишет в портрет напрямую | Только через `contractor_insight_drafts` (фаза 4) после подтверждения |
| Полнота | `coverage_pct` — сколько обязательных слотов заполнено (для баннера «не хватает для подсказок») |
| Лид ↔ контрагент | Квалификация лида может **предложить** обновление портрета (merge, не overwrite) |

---

## Схема БД

### 1. `contractor_portraits` (1:1 с `contractors`)

```sql
contractor_portraits
  contractor_id          PK, FK contractors CASCADE
  -- Слой «как с ними работать» (enum nullable = unknown)
  communication_style    varchar(32)  -- analytical | driver | amiable | expressive | unknown
  price_sensitivity      varchar(32)  -- low | medium | high | unknown
  preferred_channel      varchar(32)  -- phone | email | messenger | meeting | unknown
  decision_cadence       varchar(32)  -- fast | committee | slow | unknown
  relationship_trust     varchar(32)  -- new | stable | strained | unknown
  -- Текстовые слоты
  success_criteria       text NULL    -- «что для них успех перевозки»
  typical_objections     json NULL    -- ["дорого", "долго согласовывают документы"]
  internal_notes         text NULL    -- внутренняя памятка (не для клиента)
  -- Мета
  coverage_pct           tinyint UNSIGNED DEFAULT 0  -- 0..100, пересчёт в сервисе
  portrait_updated_at    timestamp NULL
  updated_by             FK users NULL
  created_at / updated_at
```

### 2. Расширение `contractor_contacts`

```sql
contractor_contacts
  + role_in_deal         varchar(32) NULL  -- decision_maker | influencer | gatekeeper | executor | finance | unknown
  + communication_notes  text NULL         -- стиль, когда звонить, табу-темы
```

(`is_decision_maker` оставляем для обратной совместимости; UI синхронизирует с `role_in_deal`.)

### 3. Расширение `contractor_interactions` («итог контакта»)

```sql
contractor_interactions
  + contractor_contact_id   FK contractor_contacts NULL
  + outcome_code              varchar(32) NULL  -- reached | no_answer | callback | objection | agreed_next | refused
  + next_contact_at           timestamp NULL
  + objection_tags            json NULL         -- ["price", "timing", "competitor", "documents"]
  + merge_to_portrait         boolean DEFAULT false  -- при сохранении предложить слияние в портрет
  + mail_message_id           FK mail_messages NULL  -- если контакт зафиксирован из письма
```

### 4. `contractor_portrait_events` (аудит слияний, опционально в MVP)

Лёгкая лента: «12.06 обновлён `price_sensitivity` из итога звонка #45».

```sql
contractor_portrait_events
  id
  contractor_id
  field_key
  old_value / new_value   json
  source_type             varchar(32)
  source_id               bigint NULL
  created_by
  created_at
```

### 5. Фаза 4 (следом, не блокирует MVP UI)

`contractor_insight_drafts` — как в roadmap: `field_key`, `proposed_value`, `source_type`, `source_id`, `confidence`, `status` (pending|accepted|rejected).

### 6. Почта (фаза 2a, параллельно)

Дополнить `mail_messages`:

```sql
  + internet_message_id   varchar(255) NULL UNIQUE  -- Message-ID, дедуп с Outlook/IMAP
  + mailbox_account_id      FK mail_accounts NULL
  + synced_at               timestamp NULL
```

Таблица `mail_accounts` (ящик для **чтения**, не обязательно отправки из CRM):

**MVP с учётом users:** логин = `users.email`, пароль для sync — `users.mail_imap_secret` (см. roadmap 2a). `mail_accounts` опционально: только `user_id`, `sync_enabled`, `last_sync_at`, `last_sync_error`; host/port — в `config/mail_sync.php` (reg.ru общий для всех).

```sql
mail_accounts
  id
  user_id                 FK users UNIQUE  -- один ящик на менеджера
  label                   -- users.name / email
  imap_host / imap_port / encryption  -- NULL → из config
  username                -- NULL → users.email
  secret_encrypted        -- NULL → users.mail_imap_secret (предпочтительно один источник)
  sync_enabled            boolean DEFAULT true
  last_sync_at / last_error
  created_at / updated_at
```

```sql
users
  + mail_sync_enabled       boolean DEFAULT true
  + mail_imap_secret        text NULL  -- Crypt::encryptString, обновляется при login/смене пароля
  + mail_last_sync_at       timestamp NULL
  + mail_last_sync_error    varchar(500) NULL
```

---

## Сервисы (backend)

| Класс | Назначение |
|-------|------------|
| `ContractorPortraitService` | CRUD портрета, `recalculateCoverage()`, merge из interaction/lead |
| `ContractorContextBuilder` | Снимок для MCP/UI: портрет + контакты + последние 5 interactions + открытые лиды + сводка заказов + последние mail threads (когда есть) |
| `ContractorPortraitCoverage` | Список пустых обязательных слотов → подсказки в UI |

**MCP:** расширить `get_contractor` или добавить `get_contractor_portrait_context` (лимит токенов, без сырых тел писем после purge).

---

## UI — вкладка «Портрет» в карточке контрагента

**Место:** `Contractors/Index.vue` — новая вкладка между **Контакты** и **Коммуникации** (или первая подсекция в «Коммуникации» — решение: **отдельная вкладка** «Портрет», icon `UserCircle`).

### Макет (desktop)

```
┌─────────────────────────────────────────────────────────────────┐
│ [Общие] [Реквизиты] … [Контакты] [Портрет] [Коммуникации] …     │
├─────────────────────────────────────────────────────────────────┤
│ Портрет клиента          Полнота: ████████░░ 72%  [? что это]   │
│ ⚠ Не хватает: ЛПР в карте контактов, типичные возражения        │
├──────────────────────────────┬──────────────────────────────────┤
│ Как с ними работать          │ Карта людей (из вкладки Контакты) │
│ Стиль общения    [select ▼]  │ • Петров — ЛПР — «кратко, цифры» │
│ Чувствит. к цене [select ▼]  │ • Сидорова — бухгалтерия          │
│ Предп. канал     [select ▼]  │ [Перейти к контактам]             │
│ Скорость решений [select ▼]  │                                   │
│ Доверие          [select ▼]  │                                   │
│ Что для них успех [textarea] │                                   │
│ Возражения [теги + input]    │                                   │
├──────────────────────────────┴──────────────────────────────────┤
│ Последние контакты (5)                    [+ Зафиксировать итог] │
│ 03.06 · звонок · Петров · договорились КП │ objection: price      │
│ 28.05 · email · нет ответа               │                       │
├─────────────────────────────────────────────────────────────────┤
│ Переписка (когда включён sync)     │ Предложения AI (фаза 4)    │
│ 2 цепочки за 90 дней               │ (пусто / черновики HITL)   │
└─────────────────────────────────────────────────────────────────┘
```

### Модалка «Зафиксировать итог контакта»

Обязательные: дата, канал, **с кем** (контакт), **исход** (`outcome_code`), краткий итог (1–3 предложения).  
Опционально: теги возражений, **следующий контакт**, чекбокс «обновить портрет» (показать diff полей перед сохранением).

### Баннер в лиде (тонкий MVP+)

Если `counterparty_id` задан и `coverage_pct < 50` — chip: «Портрет клиента неполный» → ссылка на контрагента, вкладка Портрет.

### Квалификация лида → портрет

На сохранении лида (вкладка квалификации): если заполнены `authority` / `budget` / `need` — кнопка «Перенести в портрет контрагента» (модалка merge).

---

## Покрытие (coverage) — обязательные слоты для v1

| # | Слот | Вес |
|---|------|-----|
| 1 | Хотя бы один контакт с `role_in_deal` ≠ unknown | 20% |
| 2 | `communication_style` ≠ unknown | 15% |
| 3 | `preferred_channel` ≠ unknown | 10% |
| 4 | `success_criteria` не пусто | 20% |
| 5 | `typical_objections` ≥ 1 тег ИЛИ interaction с objection за 180 дней | 15% |
| 6 | Interaction за последние 90 дней | 20% |

Порог для ассистента «давать тактику по клиенту»: **≥ 60%**.

---

## Критерии готовности MVP (Definition of Done)

- [ ] Миграции + модели + сохранение из карточки контрагента
- [ ] Вкладка «Портрет» + модалка «итог контакта»
- [ ] `ContractorContextBuilder` в `get_contractor` (MCP)
- [ ] Баннер неполноты в лиде (если есть counterparty)
- [ ] Статья в Книге: чеклист полей портрета (1 страница, ссылка из баннера)
- [ ] Feature-тест: сохранение портрета, merge из interaction

---

## Зависимости

| Зависимость | Блокирует MVP? |
|-------------|----------------|
| Портрет UI/БД | **Нет** — можно релизить первым |
| Почта ingest (2a) | Нет для UI; **Да** для автоконтекста из переписки |
| `contractor_insight_drafts` | Нет — ручной ввод достаточен для старта |
| Inbound IMAP UI (2b) | Нет — менеджеры остаются в Outlook |
