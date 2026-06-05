# Commercial Intelligence — фазы 0 и 1 (реализовано)

> **Полная дорожная карта и черновики фаз 2–7:** [commercial-intelligence-roadmap.md](./commercial-intelligence-roadmap.md)  
> **P0 (портрет + ingest почты):** [contractor-portrait-mvp.md](./contractor-portrait-mvp.md)

## Контекст

- **Nextcloud** — только хранилище файлов (возможно позже редактирование по ссылке). Встраивание CRM в NC не делаем.
- **Почта** — модуль в CRM (не NC Mail), для исходящих писем и будущего IMAP/AI.
- **Персональные данные в письмах** — раз в ~6 месяцев для сообщений без флага «важно» сохраняется краткий контекст (`retention_summary`), тело очищается (`mail:purge-non-important-bodies`).

## Фаза 0 — Activity Ledger + КП

| Компонент | Назначение |
|-----------|------------|
| `activity_events` | Единая лента: этапы БП, КП, письма, задачи |
| `ActivityLedgerService` | Запись и чтение таймлайна |
| `lead_offers` + `sent_at`, `title` | Статус отправки КП |
| API `GET /leads/{lead}/activity-timeline` | JSON для UI |
| Вкладка «Коммуникации» в лиде | Компонент `ActivityTimeline` |

События пишутся при смене этапа БП, подготовке КП (`leads.proposal`), отправке письма (фаза 1).

## Фаза 1 — Исходящая почта

| Компонент | Назначение |
|-----------|------------|
| `mail_threads`, `mail_messages` | Цепочки и сообщения |
| `CommercialMailService` | SMTP-отправка, привязка к лиду/КП |
| Раздел **Почта** (`visibility.area:mail`) | Список цепочек, compose |
| `POST /leads/{lead}/offers/{offer}/send-email` | Отправка КП из визарда лида |
| `commercial:check-offer-mail-nudges` | Задача «нет ответа» (по `no_reply_nudge_days` на этапе БП) |
| `mail:purge-non-important-bodies` | Ретеншн тел писем |

### Настройка

- SMTP: стандартный `config/mail.php` / `.env` (`MAIL_*`).
- Ретеншн: `MAIL_RETENTION_PURGE_MONTHS` (по умолчанию 6).
- Напоминание без ответа: `COMMERCIAL_OFFER_NO_REPLY_NUDGE_DAYS` (по умолчанию 3) или поле `no_reply_nudge_days` на этапе бизнес-процесса.

### Роли

В настройках ролей добавлена область **«Почта»** (`mail`). Для отправки КП из лида достаточно области `leads`.

### После деплоя

```bash
php artisan migrate
php artisan test --compact tests/Unit/ActivityLedgerServiceTest.php tests/Feature/LeadOfferMailSendTest.php
```

### Синхронизация IMAP (фаза 2a) ✅

- `config/mail_sync.php`, команда `mail:sync` (cron каждые 10 мин).
- Пароль почты — в карточке пользователя (`mail_imap_secret`, encrypted).
- Фильтр ingest по доменам контрагентов (`contractors.mail_sync_domains`).
- HTML-тело входящих + читаемый plain (`MailHtmlSanitizer`, `MailMessageBodyPresenter`).
- На сервере нужна PHP extension **imap** (`php-imap` / `docker-php-ext-install imap`).
- Первый прогон: `php artisan mail:sync --user=ID --days=30`

### Почта в UI (фаза 2b) 🚧

- Раздел **Почта**: цепочки, reply, compose, флаг «важно», вложения исходящих.
- Отправка — **SMTP** (`CommercialMailService`), `From` = `users.email` менеджера.
- MCP: `send_mail`, `reply_mail_thread`.
- Admin/supervisor: папки «Ящики» по владельцам.
- Мастер заказа: блок почты на вкладке «Лента».
- **Backlog:** стабильный import вложений входящих; push по новому входящему.
