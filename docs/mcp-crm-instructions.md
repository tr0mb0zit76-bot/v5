# MCP CRM «Автоальянс» — инструкции сервера

Канонический текст совпадает с `App\Mcp\Servers\CrmServer` (`#[Instructions(...)]`).
При добавлении tools обновляйте оба места и переподключите MCP в Cursor (или обновите кэш `mcps/user-v5-crm-*/INSTRUCTIONS.md`).

```
MCP-сервер CRM «Автоальянс»: чтение сущностей, запись задач и диспозиции, Книга продаж.

- get_user_context — роль и области видимости
- search_orders / get_order / get_order_timeline / list_order_documents
- get_order_field_lexicon — русские названия полей и синонимы
- search_contractors / get_contractor / create_contractor
- create_fleet_driver / create_fleet_vehicle — водитель и авто (модалки в заказе)
- search_tasks / get_task / create_task
- add_order_note — заметка в ленту заказа
- update_order_field — одно поле заказа (whitelist)
- update_order_route_actual — фактическая погрузка/выгрузка
- upsert_disposition_entry — ячейка диспозиции (утро/вечер)
- search_sales_book_articles / get_sales_book_article / upsert_sales_book_article / get_sales_book_quality_insights / get_sales_book_quiz_insights
- get_ai_usage_insights — аналитика обращений к AI (admin / settings_system)
- get_trainer_coaching_insights — зацикливание и коучинг в тренажёре (аналитика тренажёра / settings_system)
- get_manager_sales_coaching_insights — Outcome Intelligence по лидам (область leads / settings_system)
- get_print_form_templates_insights — шаблоны DOCX, базовые условия, диагностика печати (settings_system)
- get_order_intake_draft / list_order_intake_drafts / create_order_intake_draft_from_text — черновики заявок (файл или текст)
- search_mail_threads / get_mail_thread / get_mail_sync_status / send_mail / reply_mail_thread — переписка, IMAP sync и отправка из CRM

Аутентификация: Bearer Sanctum token.
```

## Новые tools (2026-06)

| Tool | Когда вызывать |
|------|----------------|
| `create_contractor` | Создать контрагента: `type` + `name` или полный ИНН (автозаполнение DaData). В ответе `show_path`. |
| `create_fleet_driver` | Модалка «Водитель»: `carrier_contractor_id`, `full_name`, опционально паспорт/ВУ/телефон. |
| `create_fleet_vehicle` | Модалка «Авто»: `owner_contractor_id`, госномера или марки тягача/прицепа. |
| `create_order_intake_draft_from_text` | Пользователь описывает заявку на перевозку текстом → `draft_id`, `wizard_path`, `wizard_patch`. MCP не открывает UI; пользователь переходит по `wizard_path` (command bar в CRM — автоматически). |
| `search_mail_threads` | Контекст переписки с клиентом, поиск по теме/email/тексту |
| `get_mail_thread` | Полные письма цепочки по `thread_id` |
| `get_mail_sync_status` | Ошибки IMAP (`mail_last_sync_error`), время последнего sync |
| `send_mail` | Отправить новое письмо (`subject`, `body`, `to`, опционально `cc`, `lead_id`, `order_id`) → `thread_id`, `message_id` |
| `reply_mail_thread` | Ответ в цепочку (`thread_id`, `body`, опционально `to`, `cc`) → `thread_id`, `message_id` |
| `get_print_form_templates_insights` | Шаблоны DOCX и базовые условия: `code` или `query`, диагностика, почему пункты не попали в черновик (settings_system / Юрик) |
