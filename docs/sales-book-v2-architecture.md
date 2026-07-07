# Книга продаж 2.0 — страницы, блоки, свойства и представления

## Контекст

Текущая Книга продаж уже закрывает базовый контур базы знаний:

- дерево страниц в `sales_book_articles` (`parent_id`, `sort_order`);
- тело статьи в `markdown_content`;
- статусы `draft` / `published`;
- `tags`, `cover_image_path`;
- web UI в `resources/js/Pages/SalesAssistant/Book.vue` через `TiptapEditor`;
- обратная связь и качество (`sales_book_article_feedback`, `SalesBookQualityInsightsService`);
- тесты/обучение (`sales_book_quiz_attempts`, `SalesBookQuizInsightsService`);
- MCP-инструменты `search_sales_book_articles`, `get_sales_book_article`, `upsert_sales_book_article`.

Ограничение текущей модели: статья — в основном markdown-страница. В ней сложно стабильно хранить структуру, строить представления, переиспользовать блоки, делать точный импорт/экспорт, связывать материал с ролями, этапами продаж, продуктами и сценариями.

Задача Книги продаж 2.0 — не интегрироваться с Collabis/Notion/AppFlowy, а забрать полезные архитектурные паттерны и реализовать их нативно в CRM.

## Что полезно забрать из Collabis и аналогов

### Collabis API / SDK

`@collabis/client` полезен как MIT-референс API-дизайна:

- `pages` — документ как адресуемая сущность с родителем, заголовком, cover, свойствами;
- `blocks` — документное тело как дерево блоков;
- `databases` — коллекции страниц с типизированными свойствами;
- `views` — сохранённые представления данных (`table`, `board` и т.п.);
- `search` — единая точка поиска по workspace / parent / database;
- block builders (`paragraph`, `heading`, `toDo`, `callout`, `table`, `bookmark`) — удобный слой для программной генерации контента;
- cursor pagination и структурированная модель ошибок.

Код SDK можно смотреть и частично заимствовать как MIT, но серверного self-hosted Collabis engine публично не найдено.

### Self-hosted аналоги

- **AFFiNE** — хороший референс по связке docs + whiteboard + databases, но лицензирование и server-компоненты неоднозначны. Использовать как продуктовый ориентир, код не тащить без отдельной проверки.
- **AppFlowy** — сильная реализация database views, но AGPL/open-core. Брать только идеи.
- **Colanode** — local-first workspace: docs, chat, files, databases. Полезен для понимания offline/sync, но лицензию проверять отдельно.
- **Bloc** — self-hosted Notion-compatible API, Apache-2.0. Интересен как пример REST API и модели совместимости.
- **Open-Silong** — MIT, молодой Notion-inspired проект с blocks + database views. Можно смотреть UI/структуру, но зрелость низкая.
- **Docmost / docmost-db** — ближе к wiki/Confluence; полезен для permissions, nested docs, embedded database views.

## Целевая модель

### Страница

Существующая `SalesBookArticle` остаётся корневой сущностью. Она должна стать аналогом `page`:

- `id`
- `parent_id`
- `title`
- `status`
- `sort_order`
- `tags`
- `cover_image_path`
- `properties`
- `content_format`
- `markdown_content` как legacy/cache
- `created_by`, `updated_by`

Новые поля лучше добавлять постепенно:

- `properties` JSON;
- `content_format` string default `markdown`;
- `blocks_snapshot` JSON nullable как переходный слой, если не сразу выносить блоки в отдельную таблицу.

### Блоки

Вариант MVP: JSON-снимок блоков прямо на статье (`blocks_snapshot`).  
Вариант P2: отдельная таблица `sales_book_article_blocks`.

Рекомендуемый P2-формат:

- `id`
- `sales_book_article_id`
- `parent_block_id`
- `type`
- `sort_order`
- `payload` JSON
- `created_by`, `updated_by`
- timestamps

Поддерживаемые типы на старт:

- `paragraph`
- `heading_1`, `heading_2`, `heading_3`
- `bulleted_list_item`
- `numbered_list_item`
- `todo`
- `callout`
- `quote`
- `code`
- `divider`
- `table`
- `bookmark`
- `quiz`
- `crm_link`

Важно: Tiptap остаётся редактором, но сохраняет/читает нормализованный blocks JSON. Markdown/HTML становится экспортом, а не единственным источником правды.

### Свойства

`properties` нужны не как произвольный мусор, а как типизированный каталог.

Минимальный набор:

- `audience_role` — менеджер, руководитель, логист, новичок;
- `sales_stage` — знакомство, квалификация, расчёт, КП, возражения, закрытие;
- `product_area` — перевозка, Traklo, документы, управленка, скрипты, КП;
- `owner_user_id`;
- `next_review_at`;
- `source` — manual, mcp, ai, import, crm_event;
- `related_route_names`, `related_visibility_areas`;
- `ai_summary`, `ai_keywords`.

Для стабильности нужен каталог свойств:

- `SalesBookPropertyCatalog` в PHP;
- зеркало опций на фронт через Inertia;
- валидация `properties` при сохранении.

### Представления

Представления — это saved views для Книги, не отдельные страницы.

Таблица `sales_book_views`:

- `id`
- `name`
- `slug`
- `scope` (`workspace`, `user`)
- `owner_user_id`
- `view_type` (`tree`, `table`, `board`, `list`)
- `filters` JSON
- `sorts` JSON
- `group_by`
- `visible_properties` JSON
- `is_default`

Стартовые системные views:

- `Все страницы` — дерево;
- `Черновики`;
- `Нуждаются в обновлении`;
- `По этапам продаж` — board по `sales_stage`;
- `По ролям` — board/table по `audience_role`;
- `Материалы для новичков`;
- `Используется AI` — статьи, которые цитировал ассистент;
- `Плохая обратная связь` — связка с `SalesBookQualityInsightsService`.

### Поиск

Текущий поиск `LIKE` по `title`, `markdown_content`, `tags` можно оставить как fallback.

Целевой поиск:

- индексировать `title`;
- plaintext из blocks;
- `tags`;
- типизированные `properties`;
- feedback/quality signals;
- связи с quiz и скриптами.

Для Laravel MVP можно начать с MySQL FULLTEXT, если схема/движок позволяет. Если нет — оставить service abstraction `SalesBookSearchService`, чтобы потом заменить на Meilisearch/Scout без переписывания контроллеров.

## API и сервисы

### Внутренние сервисы

- `SalesBookBlockNormalizer` — нормализует Tiptap/HTML/Markdown в blocks.
- `SalesBookMarkdownExporter` — blocks → Markdown для MCP/AI.
- `SalesBookHtmlExporter` — blocks → HTML для UI/preview.
- `SalesBookPropertyCatalog` — доступные свойства, типы, опции.
- `SalesBookViewService` — применяет filters/sorts/grouping.
- `SalesBookSearchService` — поиск + excerpt + matched_in.
- `SalesBookImportService` — Markdown/HTML/JSON import.
- `SalesBookArticleVersionService` — версии и diff.

### MCP

Существующие tools сохранить, но расширить:

- `search_sales_book_articles`
  - добавить фильтры по `properties`, `status`, `view_slug`;
  - возвращать `matched_in`, `properties`, `breadcrumb`, `updated_at`, `quality_flags`.
- `get_sales_book_article`
  - добавить `format: markdown|blocks|html`;
  - возвращать `blocks` только по запросу, чтобы не раздувать ответы.
- `upsert_sales_book_article`
  - принять `properties`;
  - принять `blocks` или `markdown_content`;
  - если пришёл Markdown — нормализовать и сохранить оба слоя.
- новый `list_sales_book_views`;
- новый `upsert_sales_book_view` только для admin/supervisor/settings.

### Web UI

`SalesAssistant/Book.vue` стоит развивать в три режима:

- `Tree` — текущая навигация слева;
- `Table` — строки страниц + свойства;
- `Board` — группировка по роли/этапу/status.

Редактор:

- боковая панель свойств справа;
- блоки в центре;
- actions сверху: publish/draft, review date, duplicate, move, export;
- quality panel: feedback, quiz, AI usage, last review.

## Миграционный план

### Фаза 1 — свойства и views без ломки редактора

Цель: получить пользу от properties/views, не меняя Tiptap content engine.

Изменения:

- добавить `properties` JSON, `content_format`;
- добавить `SalesBookPropertyCatalog`;
- добавить системные views на backend;
- UI: компактный переключатель views в навигации; статья остаётся главным контентом без дублирующего списка над обложкой;
- MCP: фильтры по properties;
- тесты на фильтры и права.

Риск низкий: `markdown_content` остаётся источником тела.

### Фаза 2 — block snapshot

Цель: начать хранить структурный контент без отдельной сложной таблицы.

Изменения:

- добавить `blocks_snapshot`;
- добавить normalizer/exporters (`SalesBookBlockSnapshotService`);
- сохранять при редактировании и импорте;
- MCP `get` умеет `format=blocks`;
- AI/upsert может создавать blocks через builder-like API.

Статус локально (2026-07-07): foundation реализован. `markdown_content` остаётся источником правды, `blocks_snapshot` хранит deterministic JSON (`sales_book_blocks_v1`) с блоками `heading`, `paragraph`, `list`, `todo_list`, `table`, `code`, `quote`, `image`, `quiz`. `upsert_sales_book_article` принимает `markdown_content` или `blocks`; `get_sales_book_article(format=blocks|both)` отдаёт snapshot, для старых статей без snapshot строит fallback на лету.

Риск средний: полноценный converter Tiptap ↔ blocks ещё не нужен; редактор пока работает через Markdown.

### Фаза 3 — embedded database views

Цель: внутри статьи можно вставить `database_view` / `article_collection` блок.

Примеры:

- “Материалы для новичка” внутри страницы руководства;
- “Возражения по цене” как table/board;
- “Что обновить” как view по `review_status`.

Статус локально (2026-07-07): foundation реализован. Поддержан block type `article_collection`; в Markdown он хранится как fenced directive `sales-book-view` с JSON (`title`, `view_slug`, `filters`, `limit`, `layout`). `SalesBookEmbeddedCollectionService` резолвит подборки через `SalesBookViewService`, исключает текущую статью и отдаёт строки для UI. В режиме чтения служебный directive скрывается, вместо него показываются карточки материалов. В редактор добавлена настройка вставки подборки: view, заголовок, лимит, layout и фильтры по роли/этапу/направлению. Боковая навигация получила локальный поиск и фильтры по роли, этапу и направлению. Отдельный `review_status` в MVP убран: для публикации уже есть статус статьи (`draft` / `published`), а проверку актуальности лучше делать отдельным workflow в фазе 4.

### Фаза 4 — версии и review workflow

Цель: сделать Книгу управляемой, а не просто набором страниц.

Изменения:

- `sales_book_article_versions`;
- diff Markdown/blocks;
- reviewer / approved_at;
- next review reminders;
- quality insights учитывает `next_review_at`, feedback и AI usage.

### Фаза 5 — advanced search / AI context

Цель: точнее отдавать статьи менеджеру и AI.

Изменения:

- `SalesBookSearchService` — базовый backend-сервис уже выделен из MCP;
- индекс plaintext blocks;
- скоринг по title/tags/properties/feedback;
- “контекстные подборки” для command bar и тренажёра.

## Что не делать

- Не интегрировать Collabis как внешний сервис.
- Не добавлять `@collabis/client` в зависимости CRM.
- Не внедрять AGPL/open-core код AppFlowy/AFFiNE без отдельной юридической проверки.
- Не переписывать редактор сразу на новый block engine.
- Не ломать существующие MCP tools и опубликованные статьи.

## Acceptance criteria

Фаза 1 считается готовой, если:

- текущие статьи открываются без миграции контента;
- у статьи можно редактировать properties;
- есть минимум 4 системных views;
- MCP search умеет фильтровать по properties;
- feedback/quiz продолжают работать;
- опубликованные статьи остаются доступны AI;
- тесты покрывают read/write permissions, filters, views, backward compatibility.

Фаза 2 считается готовой, если:

- статья может храниться как blocks snapshot;
- Markdown export совпадает по смыслу с текущим `markdown_content`;
- `upsert_sales_book_article` принимает markdown и blocks;
- `get_sales_book_article(format=blocks)` возвращает стабильную структуру;
- старые статьи без blocks продолжают читаться через markdown fallback.

## Практический первый PR

Минимальный первый PR:

1. Миграция: `properties`, `content_format` на `sales_book_articles`.
2. `SalesBookPropertyCatalog`.
3. `SalesBookViewService` с системными views без таблицы пользовательских views.
4. Расширение `SalesBookMcpService::search()` фильтрами по properties.
5. UI в `Book.vue`: компактный переключатель `Дерево / Таблица / По этапам`.
6. Тесты:
   - properties сохраняются;
   - views фильтруют статьи;
   - MCP search видит properties;
   - старые markdown-статьи читаются как раньше.

Это даст ощутимую пользу без риска большой миграции редактора.
