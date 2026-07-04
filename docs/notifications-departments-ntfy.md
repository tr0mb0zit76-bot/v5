# Уведомления: подразделения, колокольчик, Firebase (FCM)

Операционная документация для деплоя и администрирования.

## Подразделения

Таблицы: `departments`, `department_user` (pivot: `is_primary`, `receives_approvals`).

- **Основное подразделение** — маршрут «чьё» событие (менеджер / инициатор).
- **Согласования за подразделения** — кто получает approval-уведомления (руководитель; у главного — multiselect).

Настройка: **Настройки → Пользователи**. Seed: 3 подразделения («Подразделение 1–3») — переименуйте под реальные названия.

Сервисы:

- `App\Support\UserDepartmentSync` — сохранение из формы пользователя
- `App\Services\Notifications\NotificationRecipientResolver` — выбор получателей

## In-app колокольчик

- `CabinetInAppNotification` → канал `database`
- API: `cabinet-notifications.summary`, `read-all`, `unread`, `destroy`
- Компонент: `resources/js/Components/Layout/CrmNotificationBell.vue`

В браузере все события видны в колокольчике. Push на телефон **не обязателен** для этого.

## Firebase Cloud Messaging (мобильный APK)

Единственный push-канал для **срочных** событий в приложении «Автоальянс Чат».

### Включение на сервере (`.env`)

```env
FCM_ENABLED=true
FCM_PROJECT_ID=your-firebase-project-id
FCM_CREDENTIALS=/path/to/firebase-service-account.json
```

Whitelist видов push: `config/fcm.php` → `push_kinds`:

- `chat_message` — мессенджер
- `order_document_approval` — подписать заявку
- `order_document_approved` — заявка подписана
- `order_closing_documents_required` — закрывающие документы
- `contractor_limit_approval` — согласование лимита

SLA, комментарии к задачам и прочее — **только колокольчик**, без FCM.

### Android APK

1. Firebase Console → проект → Android app `ru.avtoaliyans.crm.messenger`
2. Скачать `google-services.json` → `android/app/google-services.json` (шаблон: `google-services.json.example`)
3. Пересобрать APK в Android Studio
4. Каналы уведомлений создаются в `MainActivity`: Чаты / Заказы / Бухгалтерия

### Маршрутизация

`CabinetNotifier` и `OrderClosingDocumentsNotificationService` → колокольчик + `MobilePushService` (если `FCM_ENABLED` и у пользователя есть `user_mobile_devices.fcm_token`).

## Маршрутизация approval

| Событие | Подразделение |
|---------|---------------|
| Лимит контрагента | primary инициатора |
| Согласование заявки | primary менеджера заказа + подписанты |

Fallback: admin при `NOTIFICATIONS_APPROVAL_INCLUDE_ADMINS=true` (по умолчанию false).

## ntfy (снято с поддержки)

Ранее использовался sidecar `deploy/ntfy/` и `users.ntfy_topic`. Заменён на FCM. Колонка `ntfy_topic` удалена миграцией `2026_07_04_171344_drop_ntfy_topic_from_users_table.php`.

## Тесты

```bash
php artisan test --compact tests/Feature/CabinetInAppNotificationsTest.php
php artisan test --compact tests/Feature/MobilePushServiceTest.php
php artisan test --compact tests/Feature/NotificationDepartmentRoutingTest.php
php artisan test --compact tests/Feature/ContractorLimitApprovalTest.php
```
