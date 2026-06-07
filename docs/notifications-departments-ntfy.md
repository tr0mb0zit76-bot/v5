# Уведомления: подразделения, колокольчик, ntfy

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

## ntfy (push)

Включение в `.env`:

```env
NTFY_ENABLED=true
NTFY_BASE_URL=https://ntfy.avtoaliyans.ru
```

Sidecar: `deploy/ntfy/` — см. `deploy/ntfy/README.md`.

Push только для:

- `order_document_approval`
- `contractor_limit_approval`

У пользователя должен быть `users.ntfy_topic`. UI генерации topic — в backlog (пока tinker/SQL).

## Маршрутизация approval

| Событие | Подразделение |
|---------|---------------|
| Лимит контрагента | primary инициатора |
| Согласование заявки | primary менеджера заказа + подписанты |

Fallback: admin при `NOTIFICATIONS_APPROVAL_INCLUDE_ADMINS=true` (по умолчанию false).

## Scoring v2 и лимиты (связанный контур)

- `contractor_risk_snapshots`, `contractor_risk_assessments`
- HITL: подтверждение руководителем; «Отправить на согласование» → `pending_approval` + уведомление
- Конфиг: `config/contractor_scoring.php`, `config/checko.php` (TTL 7 дней)

ADR в Obsidian: `Decisions/ADR Contractor Risk Scoring v2.md`.

## Тесты

```bash
php artisan test --compact tests/Feature/CabinetInAppNotificationsTest.php
php artisan test --compact tests/Feature/ContractorLimitApprovalTest.php
php artisan test --compact tests/Unit/Services/Checko/ContractorScoringCalculatorTest.php
```
