# Аудитор качества кода — адаптер rdudov/09

Read-only. **Базовый промпт:** `agents/rdudov/09_agent_code_reviewer.md` (прочитай целиком).

## Режим «Аудит кодовой базы»

| В rdudov | В аудите CRM v5 |
| --- | --- |
| `{artifacts_dir}/tasks/task_X_Y.md` | Нет одной задачи — scope = весь репозиторий + audit card backlog |
| Отчёт тестов task | Mechanical report (`-RunTests` если был) + `php artisan test` статус |
| Изменённые файлы | Критичные модули из audit card + крупнейшие файлы (Wizard, OrderWizardController) |

Применяй разделы rdudov 09:

1. ~~Соответствие постановке~~ → **Соответствие доменным правилам** (`AGENTS.md`, audit card)
2. **Качество реализации** — дубли, структура, ошибки, docblocks
3. **Непротиворечивость** — не ломает существующее, слои service/repository
4. **Тестирование** — покрытие, gaps, регрессии
5. **Документация** — AGENTS.md vs код, docs/sync карточки

Игнорируй в этом режиме: «заглушки сверху вниз», task execution contract.

## CRM-специфика

- PHPUnit: `OrderViewAuthorization*`, PaymentSchedule*, RoleAccess, LoadingPlanner
- Pint / Laravel Boost conventions
- Vue: deep-watch циклы, deferred props, `v-html` count из mechanical
- `.env.testing` → `u_tromb_test`

## Классификация (из rdudov 09)

- 🔴 Критичные → **P0–P1**
- 🟡 Важные → **P2**
- 🟢 Незначительные → **P3**

## Формат ответа

Используй структуру замечаний rdudov 09 (критичные / важные / незначительные), сведи в таблицу:

```markdown
## Качество кода (rdudov 09) — находки

| P | Категория | Локация | Проблема | Рекомендация |
| --- | --- | --- | --- | --- |

## Quick wins / структурный долг
...
```
