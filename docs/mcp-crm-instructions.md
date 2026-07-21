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
- search_leads / get_lead / update_lead_field / create_lead_next_step — лиды (карточка, whitelist-поля, следующий контакт)
- search_tasks / get_task / create_task
- add_order_note — заметка в ленту заказа
- update_order_field — одно поле заказа (whitelist)
- update_order_route_actual — фактическая погрузка/выгрузка
- upsert_disposition_entry — ячейка диспозиции (утро/вечер)
- search_sales_book_articles / get_sales_book_article / upsert_sales_book_article / get_sales_book_quality_insights / get_sales_book_quiz_insights
- get_ai_usage_insights — аналитика обращений к AI (admin / settings_system)
- get_trainer_coaching_insights — зацикливание и коучинг в тренажёре (аналитика тренажёра / settings_system)
- get_sales_script_coaching_insights — живые прохождения скриптов: исходы, возражения, слабые менеджеры, рекомендации (аналитика тренажёра / settings_system)
- get_manager_sales_coaching_insights — Outcome Intelligence по лидам (область leads / settings_system)
- get_print_form_templates_insights — шаблоны DOCX, базовые условия, диагностика печати (settings_system)
- upsert_print_form_basic_terms — прямое сохранение базовых условий cp/dp (admin / settings_system)
- submit_contractor_print_form_change — заявка на согласование условий контрагента (contractors)
- resolve_contractor_print_form_change — утвердить / отклонить / вернуть на согласование с контрагентом (руководитель)
- list_proposal_html_templates / get_proposal_html_template / create_proposal_html_template / update_proposal_html_template — HTML-шаблоны КП (settings_system): cold или clone parallel-import + тексты/картинки
- get_order_intake_draft / list_order_intake_drafts / create_order_intake_draft_from_text / extract_order_draft_from_document / apply_order_wizard_draft — черновики заявок (текст, файл base64, создание заказа с confirm_token)
- search_mail_threads / get_mail_thread / get_mail_sync_status / send_mail / reply_mail_thread — переписка, IMAP sync и отправка из CRM (search: query, mailbox_owner, mailbox_user_id; team[].thread_count в sync status)
- Управленческий учёт (`can_management_accounting` / admin):
  list_management_statement_imports, list_management_statement_lines, suggest_management_statement_line,
  allocate_management_statement_line (`remember_keyword` — обучение правила), get_management_accounting_analytics,
  list_management_expense_categories, remember_management_reconcile_rule, list_management_reconcile_rules

Аутентификация: Bearer Sanctum token (`php artisan mcp:issue-token {user} --write` для записи; по умолчанию только `mcp:read`). Ротация: перевыпуск каждые ~90 дней (`--days=90`, глобальный лимит `SANCTUM_EXPIRATION`).
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
| `upsert_print_form_basic_terms` | Прямое сохранение базовых условий `party` + `items`, опционально `contractor_id` (admin / settings_system) |
| `submit_contractor_print_form_change` | Заявка на согласование условий контрагента: `contractor_id`, `party`, `items`, опционально `manager_notes` / `yurik_summary` |
| `resolve_contractor_print_form_change` | Решение по заявке: `change_request_id`, `action` (`approve` / `reject` / `needs_counterparty`), `reason` при reject |
| `list_proposal_html_templates` | Список HTML-шаблонов КП + `stock_assets` (settings_system) |
| `get_proposal_html_template` | Карточка шаблона: placeholders, image_srcs; `include_html` для полного HTML |
| `create_proposal_html_template` | `mode=cold` (тексты + stock_asset/hero_image) или `mode=clone` (base_slug + text_replacements + images). Нужен `mcp:write` |
| `update_proposal_html_template` | Правки name/is_active / text_replacements / images / html_body. Нужен `mcp:write` |

## Лиды (MCP)

Доступ: область `leads`. Запись (`update_lead_field`, `create_lead_next_step`) — `mcp:write`. Следующий шаг также требует область `tasks`.

| Tool | Когда вызывать |
|------|----------------|
| `search_leads` | Найти лид по номеру/id/заголовку/контрагенту; опционально `status` |
| `get_lead` | Карточка + `operational_brief` (gaps/next_move) + открытые задачи + `wizard_path` |
| `update_lead_field` | Одно поле из whitelist (без закрытия won/lost) |
| `create_lead_next_step` | Задача следующего контакта + `next_contact_at` при `due_at` |

Сервис: `App\Services\Mcp\LeadMcpService`.

## Управленческий учёт (2026-06)

Доступ: `users.can_management_accounting` или admin. В `get_user_context` — поле `can_management_accounting`.

Импорт выписки на чтение строк: загрузивший или admin (как экран разнесения).

| Tool | Когда вызывать |
|------|----------------|
| `list_management_statement_imports` | Список загрузок выписок (`limit`) |
| `list_management_statement_lines` | Строки по `import_id`, опционально `status` (`pending` / `allocated`) |
| `suggest_management_statement_line` | Подсказка разнесения: эвристики + `management_reconcile_rules`; при неоднозначности — `candidates[]` (заявка, сумма, план, id графика) |
| `allocate_management_statement_line` | Подтвердить разнесение; `remember_keyword` — сохранить правило по фрагменту назначения |
| `get_management_accounting_analytics` | План/факт за `period_type` (`month` / `quarter` / `year`); в ответе `plan_source`, `variance_rows`, `payroll_variance`, `plan_snapshot` |
| `list_management_expense_categories` | Справочник статей (системные, из бюджета, пользовательские) |
| `remember_management_reconcile_rule` | Явно добавить правило (`keyword`, `allocation_type`, …) |
| `list_management_reconcile_rules` | Активные правила обучения |

Сервис: `App\Services\Mcp\ManagementAccountingMcpService`. Домен MCP: `finance` (`McpToolDomainRegistry`).
