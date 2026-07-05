# Traklo — runbook для разработчиков

> Внешние пользователи контрагента, invite-link, counterparty shell.  
> Пользовательская инструкция — в Книге продаж («Traklo для менеджера»).  
> ТЗ: `docs/external-counterparty-users-tz.md`

---

## Архитектура (MVP фаза B)

| Слой | Назначение |
|------|------------|
| `users.is_external` + `contractor_id` + `contractor_contact_id` + `external_party` | Быстрый gate «не staff» |
| Роли `counterparty_carrier` / `counterparty_customer` | Области `counterparty_*` |
| `CounterpartyOrderAccess` | Party-scope заказов (не `manager_id`) |
| `RejectExternalFromInternalRoutes` | Fail closed для CRM-маршрутов |
| `external_user_invites` | Invite-link → set password |
| `/mobile/shell/counterparty/*` | API списка заказов для external |

Messenger (фаза C): counterparty conversations, guards, mobile UI.

Customer portal (фаза D): `/portal/customer/{token}`, upload customer documents, «Написать в Traklo» в мастере заказа.

---

## Выдача доступа (staff)

1. Карточка контрагента → вкладка **Портрет** → блок **Traklo · доступ контакта**.
2. **Основной** — `PATCH` через `POST contractors/{id}/contacts/{id}/traklo/primary` → `is_traklo_primary`.
3. **Пригласить** — `POST .../traklo/invite` → создаёт/обновляет external `User`, возвращает URL `/external/invite/{token}`.
4. Менеджер копирует ссылку и отправляет контакту (WhatsApp / email / SMS вручную).

Контакт открывает ссылку → задаёт пароль → редирект в `/mobile/messenger`.

---

## APK и витрина

- Канон URL: `config/external_users.php` → `apk_url` (env `MOBILE_APP_APK_URL`, default `/downloads/traklo.apk`).
- Публичная витрина: footer **«Скачать Traklo»** в `PublicSiteShell.vue`.
- Bump APK: `npm run traklo:apk:release` + положить файл в `public/downloads/traklo.apk` (или обновить env).

Web-портал `/portal/carrier/{token}` **не снимаем** — параллельный канал.

---

## Middleware whitelist (external)

Разрешено без 403:

- `mobile/login`, `mobile/messenger`, `mobile/shell/counterparty/*`
- `messenger/*`, `portal/*`, `external/invite/*`, `logout`

Всё остальное → redirect на mobile messenger или 403 JSON.

---

## Миграции

```bash
php artisan migrate
```

Создаёт колонки, `external_user_invites`, роли counterparty, поля conversations.

---

## Тесты

```bash
php artisan test --compact tests/Feature/ExternalUserProvisionTest.php
php artisan test --compact tests/Feature/RejectExternalFromInternalRoutesTest.php
```

---

## Деплoy

1. `php artisan migrate --force`
2. `npm run build`
3. APK — только если меняли native / versionCode
4. Проверка: invite-link → set password → mobile messenger; staff `/orders` для external → 403/redirect

---

## Следующие шаги (фаза C)

- `MessengerService`: counterparty conversations, запрет mixed participants
- Панель заказов в чате, system messages → timeline
- Customer upload packing/invoice через counterparty documents API
