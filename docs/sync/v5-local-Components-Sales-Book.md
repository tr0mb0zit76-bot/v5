# Книга продаж

> Компонент CRM v5-local. Источник в git: `docs/sync/v5-local-Components-Sales-Book.md`.

## Текущее состояние

- Страницы Книги продаж: `sales_book_articles`, модель `SalesBookArticle`.
- UI: `resources/js/Pages/SalesAssistant/Book.vue`, редактор `TiptapEditor`.
- Обратная связь: `sales_book_article_feedback`, `SalesBookQualityInsightsService`.
- Тесты/обучение: `sales_book_quiz_attempts`, `SalesBookQuizInsightsService`.
- MCP: `search_sales_book_articles`, `get_sales_book_article`, `upsert_sales_book_article`; search поддерживает `view_slug` и фильтры `properties`, `get` поддерживает `format=blocks|both`, `upsert` принимает `markdown_content` или `blocks`.

## Книга продаж 2.0

Архитектурное ТЗ: `docs/sales-book-v2-architecture.md`.

Идея: не интегрироваться с Collabis/Notion/AppFlowy, а забрать полезные паттерны в нативную CRM-модель:

- `pages` → текущие `SalesBookArticle`;
- `blocks` → структурное тело статьи рядом с legacy `markdown_content`;
- `properties` → роль, этап продаж, продуктовая область;
- `views` → компактные режимы навигации Книги (`tree`, `table`, `by-stage`, `manager-materials`);
- `search` → `SalesBookSearchService`; сейчас ищет по title/content/tags/properties и подключён к UI боковой навигации, позже можно добавить отдельный индекс blocks/plaintext.

Фаза 1 реализована локально: `properties/content_format`, `SalesBookPropertyCatalog`, `SalesBookViewService`, системные views (`tree`, `table`, `by-stage`, `manager-materials`), фильтры MCP search, компактный UI-переключатель views в боковой навигации.

Фаза 2 foundation реализована локально: `blocks_snapshot`, `SalesBookBlockSnapshotService`, deterministic schema `sales_book_blocks_v1`, сохранение snapshot при web/MCP/import и sync child-links, MCP `get_sales_book_article(format=blocks|both)`, MCP `upsert_sales_book_article` с builder-like `blocks`.

Фаза 3 foundation реализована локально: block type `article_collection`, Markdown directive `sales-book-view` с JSON, `SalesBookEmbeddedCollectionService`, embedded-подборки материалов внутри статьи в UI; в редакторе есть настройка вставки подборки без ручного JSON (view, заголовок, лимит, layout, фильтры).

UX навигации: боковая панель содержит компактный переключатель views, backend-поиск и фильтры по роли/этапу/направлению. При активном поиске дерево показывается плоским списком найденных материалов; совпадения по тексту статьи выводятся с коротким excerpt.

UX редактора: toolbar уплотнён; есть одна скрепка для файлов/картинок, dropdown цвета/маркера и dropdown `Блок` для быстрых вставок `Заметка`, `Чек-лист`, `Разделитель`, `Скрипт ответа`, `Возражение`, `Мини-КП`, `Следующий шаг`, `Контрольные вопросы`.

## Синхронизация инструкций из git

| Материал | Статья БД | Скрипт |
| --- | --- | --- |
| Лиды — инструкция | id=19 «Лиды — инструкция для пользователя» | `php scripts/sync-leads-sales-book-article.php` |
| Документы (runbook) | см. `scripts/mcp-prod-upsert-documents.php` | отдельный сценарий |
| Полное руководство CRM + 7 тематических руководств | ids=256–263 под «Руководство по CRM» | `php scripts/mcp-prod-upsert-crm-user-guide.php` |

Источник правды для лидов: `docs/lead-user-guide.md` (краткая) + `docs/leads-mechanism.md` (полный регламент). После правок в git — скрипт на каждой среде (local/prod), т.к. контент статьи в БД.

Канон полного руководства: `docs/crm-user-guide.md`; тематические статьи — `crm-basics-user-guide.md`, `contractors-user-guide.md`, `messenger-user-guide.md`, `finance-user-guide.md`, `fleet-user-guide.md`, `sales-assistant-modules-user-guide.md`, `crm-admin-user-guide.md`. На production статьи ids=256–263 опубликованы.

*Обновлено: 2026-07-15.*
