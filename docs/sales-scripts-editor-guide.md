# Редактор скриптов продаж — граф, теги, шаблоны, поля

Руководство для разработчиков и редакторов сценариев. План внедрения модуля: [scripts-module-implementation-plan.md](./scripts-module-implementation-plan.md).

## Обзор

Модуль «Скрипты» — граф узлов и переходов по классам реакций клиента. Два режима:

| Режим | Страница | Назначение |
| --- | --- | --- |
| **Прохождение (Play)** | `SalesScripts/Play.vue` | Менеджер читает текст шага, выбирает реакцию, заполняет поля |
| **Редактор (Graph)** | `SalesScripts/Editor/Graph.vue` | Руководитель собирает граф на канвасе, публикует версию |

Область видимости: `sales_assistant_scripts` (легаси-ключ `scripts` в `RoleAccess`).

## Ключевые файлы

### Backend

- `SalesScriptController` — список, Play, advance сессии
- `SalesScriptEditorController` — граф, шаблоны узлов, поля захвата
- `SalesScriptPlaySessionService` — старт/advance/завершение, `saveFieldValues()`
- `SalesScriptPlayPresentationService` — текст шага с сегментами (`operator_segments`, `operator_line`)
- `SalesScriptBodyPlaceholderService` — парсинг `{code}` в теле узла (UTF-8 safe)

### Frontend

- `ScriptGraphCanvas.vue` — канвас: превью текста, ответы на карточке, облако тегов
- `Graph.vue` — боковая панель: узел, теги, шаблоны, поля разговора
- `Play.vue` — inline-поля захвата, сводка после сессии

### Модели и таблицы

| Таблица | Назначение |
| --- | --- |
| `sales_scripts`, `sales_script_versions`, `sales_script_nodes`, `sales_script_transitions` | Ядро графа |
| `sales_script_nodes.tags` (JSON) | Теги узла для фильтра на канвасе |
| `sales_script_nodes.capture_field_codes` (JSON) | Коды полей, захватываемых на этом шаге |
| `sales_script_node_templates` | Библиотека шаблонных блоков |
| `sales_script_capture_fields` | Справочник полей разговора (`code`, `label`) |
| `sales_script_play_session_field_values` | Значения полей по сессии |

Миграции: `2026_06_10_195759_*` (tags), `2026_06_10_200147_*` (шаблоны и поля).

## Редактор графа

### Канвас

- Узлы показывают превью `body`, кнопки переходов («ответы клиента») прямо на карточке
- **«+ Ответ клиента»** — добавляет переход с новым классом реакции
- **Облако тегов** над канвасом: клик подсвечивает узлы с тегом, повторный клик сбрасывает фильтр
- Высота узла динамическая по содержимому

### Боковая панель (без левой навигации по шагам)

- Редактирование выбранного узла: тип, текст, теги (чипы + Enter)
- Чекбоксы «Захват на этом шаге» для полей разговора
- Вставка `{code}` в текст из справочника полей

### Шаблоны блоков

Маршруты: `scripts.editor.node-templates.*`

- **Сохранить как шаблон** — из текущего узла
- **Вставить** — создаёт узел из шаблона (body + переходы по возможности)

### Поля разговора

Маршруты: `scripts.editor.capture-fields.*`

В тексте узла плейсхолдеры `{client_name}` разбиваются на сегменты:

- **capture** — ввод менеджера на шаге Play
- **reference** — подстановка ранее сохранённого значения той же сессии

При `advance` клиент отправляет `field_values`; сервер сохраняет в `sales_script_play_session_field_values`. После завершения сессии Play показывает сводку захваченных полей.

## Прохождение (Play)

1. `SalesScriptPlayPresentationService` строит `operator_segments` для текущего узла
2. Inline-поля для сегментов `capture` с `capture_field_codes` узла
3. Ранее введённые значения подставляются в `reference`-сегменты
4. Кнопки реакций ведут по переходам графа (как в тренажёре)

Демо-данные: `SalesScriptsDemoSeeder` — поля и плейсхолдеры на шагах `qualify` / `positive`.

## Тесты

- `tests/Feature/SalesScripts/SalesScriptEditorTest.php` — сохранение графа с `tags`
- `tests/Unit/SalesScriptBodyPlaceholderServiceTest.php` — парсинг `{code}`, UTF-8
- `tests/Unit/SalesScriptPlayPresentationServiceTest.php` — сегменты и подстановка

Feature-тесты с `RefreshDatabase` требуют MySQL (на Windows без `mysql` в PATH — гонять на CI/сервере).

## Деплой

```bash
git pull
php artisan migrate
npm run build
php artisan db:seed --class=SalesScriptsDemoSeeder  # опционально
```

*Обновлено: 2026-06-10.*
