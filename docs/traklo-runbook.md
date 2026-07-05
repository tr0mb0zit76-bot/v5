# Traklo — runbook для разработчиков

> External users, invite-link, counterparty shell, messenger, web-portals.  
> **Пользователям:** `docs/traklo-manager-guide.md`, `docs/traklo-counterparty-guide.md` → Книга продаж.  
> **ТЗ:** `docs/external-counterparty-users-tz.md`

---

## Что **не** делаем (≠ desktop-кабинет)

**«Desktop-кабинет контрагента в CRM»** (ТЗ §2) — это **отложенная** идея: полноценный Inertia-интерфейс CRM на ПК для external (грид заказов, реестр документов, настройки как у staff). **Не реализовано и не планируется в MVP.**

**Реализовано вместо этого:**

| Канал | Маршруты / UI |
| --- | --- |
| Traklo mobile shell | `/mobile/messenger`, `/mobile/shell/counterparty/*` |
| Guest web | `/portal/carrier/{token}`, `/portal/customer/{token}` |
| Invite | `/external/invite/{token}` |

External user с `is_external=true` получает **403 / redirect** на desktop `/orders`, `/leads`, `/finance` (`RejectExternalFromInternalRoutes`).

---

## Архитектура (фазы B–D)

| Слой | Назначение |
| --- | --- |
| `users.is_external`, `contractor_id`, `contractor_contact_id`, `external_party` | Gate «не staff» |
| Роли `counterparty_carrier` / `counterparty_customer` | Области `counterparty_*` |
| `CounterpartyOrderAccess` | Party-scope заказов |
| `RejectExternalFromInternalRoutes` | Fail closed для CRM |
| `external_user_invites` | Invite → set password |
| `conversations.channel=counterparty` | Thread на контрагента + party |
| `CounterpartyConversationService` | Guards, system messages, orders panel |
| `/mobile/shell/counterparty/*` | Orders + document upload API |
| `/portal/customer/{token}` | Customer document portal |
| `/portal/carrier/{token}` | Carrier fleet + documents (legacy) |

---

## Миграции

```bash
php artisan migrate
```

Файлы `2026_07_05_*`: users/contacts, invites, roles, conversations, chat_messages.order_id, nullable password.

---

## API (основное)

| Маршрут | Кто |
| --- | --- |
| `POST contractors/{c}/contacts/{contact}/traklo/primary` | Staff |
| `POST contractors/{c}/contacts/{contact}/traklo/invite` | Staff |
| `GET/POST external/invite/{token}` | Guest |
| `POST messenger/conversations/open-counterparty` | Staff |
| `GET messenger/counterparty-contacts` | Staff |
| `GET mobile/shell/counterparty/orders` | External |
| `POST orders/{order}/portal-invites/customer` | Staff |
| `POST orders/{order}/portal-invites/carrier` | Staff |
| `GET/POST portal/customer/{token}/documents` | Guest |
| `GET/POST portal/carrier/{token}/*` | Guest |

---

## Middleware whitelist (external)

`mobile/login`, `mobile/messenger`, `mobile/shell/counterparty/*`, `messenger/*`, `portal/*`, `external/invite/*`, `logout`.

---

## Тесты

```bash
php artisan test --compact tests/Feature/ExternalUserProvisionTest.php
php artisan test --compact tests/Feature/RejectExternalFromInternalRoutesTest.php
php artisan test --compact tests/Feature/CounterpartyMessengerTest.php
php artisan test --compact tests/Feature/OrderCustomerPortalTest.php
```

---

## Деплой

1. `php artisan migrate --force`
2. `npm run build`
3. Smoke (см. ниже)
4. APK — только при смене native / `versionCode` (`npm run traklo:apk:release`)

---

## Smoke-чеклист (prod / staging)

- [ ] Портрет контрагента → primary + invite → set password → Traklo
- [ ] External: только свои заказы; `/orders` → redirect/403
- [ ] Staff: «Написать в Traklo» из заказа (customer + carrier)
- [ ] Два counterparty-треда на заказ, без mixed group
- [ ] «Ссылка заказчику» → upload на customer portal
- [ ] «Ссылка перевозчику» → carrier portal работает
- [ ] Footer / transport-request: «Скачать Traklo»
- [ ] Деактивация external User блокирует login

---

## Книга продаж (публикация)

Канон markdown в `docs/`:

- `docs/traklo-manager-guide.md` → родитель **«Руководство по CRM»**, заголовок **«Traklo для менеджера»**
- `docs/traklo-counterparty-guide.md` → **«Traklo для контрагента»**

**Локально (БД dev):**

```bash
php artisan sales-book:upsert-child-page ^
  --parent="Руководство по CRM" ^
  --title="Traklo для менеджера" ^
  --file=docs/traklo-manager-guide.md
```

**Prod через MCP:**

```bash
php scripts/mcp-prod-upsert-traklo.php
```

---

## Конfig

- `config/external_users.php` → `apk_url` (`MOBILE_APP_APK_URL`, default `/downloads/traklo.apk`)

---

## Backlog (после MVP)

- System message в чат при upload через web portal
- Отметить критерии §15 в ТЗ после smoke
- Один contact = carrier **и** customer (отложено)
- SMS-invite (отложено)
