# Топология модулей — дубли, границы, DRY

Read-only. Фокус: **нет ли двух модулей, делающих одно и то же?**

## Что искать

### 1. Дублирующие имена и параллельные реализации

- Одинаковые `*Service.php` в разных namespace (mechanical report)
- Несколько `*Authorization` / `*Access` / `*Gate` на один concern — нужен ли канон?
- Два способа scope (manager_id vs OrderViewAuthorization vs department)
- Параллельные Catalog для одного справочника

### 2. Размытые границы доменов

Сверь `app/Services/*` с картой `docs/sync/v5-local-00-index.md`:

| Домен | Ожидаемые сервисы | Leakage в чужие домены? |
| --- | --- | --- |
| Orders | Order*, PaymentSchedule*, Fleet* | Finance logic in Order controller? |
| Finance | Finance*, ManagementAccounting* | |
| Commercial | Lead*, Pipeline*, SalesScript* | |
| Documents | OrderDocument*, Print* | |

### 3. Frontend mirrors backend chaos

- `resources/js/Pages/*` — зеркало fat backend?
- Дубли support/*.js vs backend logic
- Один domain — один каталог Pages + Components

### 4. Cross-cutting без единого home

- Visibility/RBAC → RoleAccess + middleware (один home?)
- Print forms → PrintForm* services (не размазано?)
- Grid views → GridViewService (не дублировать в каждом IndexController)

## Heuristics (grep/codegraph)

```
class \w+Catalog\b
class \w+Authorization\b
Presenter
applyOrdersVisibilityScope
serializeOrder
```

## Формат ответа

```markdown
## Топология модулей

### Дубли / параллельные реализации
| Concern | Вариант A | Вариант B | Вердикт | Единый паттерн |

### Orphan / без карточки в docs/sync
- ...

### Рекомендуемая карта модулей (1 абзац + bullet boundaries)
```

Severity: **merge** (объединить), **extract** (вынести), **document** (оставить, но описать), **delete** (legacy dead code — только если уверен).
