# SQL Security Hygiene

Короткие правила для CRM v5-local, чтобы не заносить SQL-инъекции при доработках.

## Базовое правило

- По умолчанию используем Eloquent / Query Builder: `where`, `whereIn`, `whereBetween`, `orderBy`, `delete`, `update`, `exists`.
- Пользовательский ввод не склеиваем в SQL-строку. Любые значения из request, MCP, query params, JSON payload — только через bindings или whitelist.
- Имена колонок, таблиц, direction сортировки и SQL-функции нельзя брать напрямую из пользовательского ввода. Для них нужен явный map: `field_key => column_name`.

## Raw SQL допустим

- Статические выражения без пользовательского ввода: `CASE`, `COALESCE`, `COUNT(*)`, `FIELD(status, ...)`.
- Выражения, собранные только из внутренних whitelist/Schema checks.
- `whereRaw` / `selectRaw` с bindings: `whereRaw('LOWER(name) like ?', [$value])`.

## Raw SQL запрещён

- `whereRaw("name like '%{$request->q}%'")`.
- `orderByRaw($request->sort)`.
- `DB::statement()` / `DB::unprepared()` для runtime-данных приложения.
- Любой SQL, где значение, колонка или направление сортировки пришли из request/MCP без whitelist.

## Проверка перед merge

1. Поискать: `DB::raw`, `whereRaw`, `selectRaw`, `orderByRaw`, `DB::statement`, `DB::unprepared`.
2. Для каждого raw-места ответить: откуда пришли все фрагменты SQL?
3. Если есть пользовательский ввод — заменить на Query Builder или bindings.
4. Если есть динамическая колонка/сортировка — заменить на whitelist map.

## Текущий pass 2026-07-07

- Явной склейки пользовательского ввода в raw SQL не найдено.
- `DB::unprepared()` в `OrderWizardService` заменён на Query Builder delete.
- Оставшиеся raw-места выглядят как статические выражения, bindings или SQL из внутренних whitelist/Schema checks.
