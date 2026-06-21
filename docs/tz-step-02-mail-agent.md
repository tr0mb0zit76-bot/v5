# ТЗ — Шаг 2: Агент анализа почты

**Цель:** command bar / MCP умеет **резюмировать переписку** и **предлагать черновик ответа**, опираясь на `mail_threads` / `mail_messages`.

---

## Предусловия (инфра)

| Параметр | Значение |
|----------|----------|
| `.env` | `MAIL_SYNC_ENABLED=true`, IMAP reg.ru, `DEEPSEEK_API_KEY` |
| Cron | `php artisan schedule:run` → `mail:sync` |
| Пользователь | `mail_sync_enabled`, `mail_imap_secret` после login |
| Роль | область `mail` |

---

## MVP (DoD)

| # | Задача | Статус |
|---|--------|--------|
| 2.1 | Персона «Почта» в `config/ai_agents.php` | ✅ `pochta` |
| 2.2 | Tool `summarize_mail_thread` | ✅ `MailThreadAnalysisService` |
| 2.3 | Tool `draft_mail_reply` | ✅ |
| 2.4 | Tool `suggest_lead_next_step_from_mail` | ✅ |
| 2.5 | System prompt: сначала tools, цитаты без сырых ПД после purge | ✅ CommandBar + persona |
| 2.6 | Feature-тесты MailMcpService / AgentToolRegistry | ✅ unit-тесты (MySQL для feature — см. `.env.testing`) |

**Шаг 2 закрыт (2026-06-21).** Инфра sync/cron — проверить на проде вручную.

---

## Не входит в MVP

- Письма как события в timeline заказа (roadmap отложено)
- Авто-запись в портрет (шаг 3, HITL)
- Graph API вместо IMAP
- Скачивание вложений ATI/MIME (2b.8)

---

## UI (опционально после tools)

- Кнопка «Резюме» / «Черновик ответа» на странице цепочки почты → POST command bar с контекстом thread_id
