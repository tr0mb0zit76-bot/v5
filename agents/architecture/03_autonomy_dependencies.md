# Автономность и зависимости

Read-only. **Протоколы и scripts должны работать без «ходить в интернет за промптами».**

## 1. Autonomy checklist (scripts/)

Прочитай mechanical report §autonomy.

**Норма:**
- `architecture-protocol.ps1`, `audit-protocol.ps1` — **только локальный** repo + php/composer/rg
- `agents/rdudov/*` — vendor-копии в git, не runtime fetch
- `.cursor/skills/*/SKILL.md` — в репозитории

**Opt-in сеть (допустимо, но не для ежедневного протокола):**
- `sync-rdudov-agents.ps1` — ручное обновление vendor
- `sync-docs-to-yandex.ps1` — локальный Exchange path
- `prod-plink.ps1` — prod SSH (не architecture scope)

**Красные флаги:** git clone / curl github / Invoke-RestMethod в scripts без whitelist.

## 2. Зависимости между слоями (направление)

Правильное направление:

```
HTTP/MCP → Controller → Service → Model/DB
                ↓
            Support/Catalog (read-only helpers)
Vue Page → support/*.js → Inertia props ← Controller
```

**Нарушения искать:**
- Controller → DB:: напрямую (mechanical)
- Service → HTTP response / Inertia
- Model → Service (циклы)
- Vue → business rules дублирующие backend

## 3. Composer / npm boundaries

- `require` vs `require-dev` — dev tools не в prod path?
- Дубли JS libs (проверить package.json на overlapping UI libs)
- Laravel packages — каждый с явной ролью (Sanctum, Inertia, Ziggy)

## 4. External runtime dependencies (architecture)

Не блокеры протокола, но для карты:

- Gotenberg (PDF), IMAP (mail), MySQL, Nextcloud? — где абстракция, где hardcode

## 5. Skills & agents self-contained

| Артефакт | Должен быть в git |
| --- | --- |
| audit-protocol skill | `.cursor/skills/audit-protocol/` |
| architecture-protocol skill | `.cursor/skills/architecture-protocol/` |
| rdudov vendor | `agents/rdudov/` |
| Orchestrators | `agents/audit/`, `agents/architecture/` |

## Формат ответа

```markdown
## Автономность

| Компонент | Автономен? | Зависимость | Рекомендация |

## Граф зависимостей (нарушения слоёв)
| From | To | Файл | Fix |

## Внешние сервисы (inventory)
| Сервис | Абстракция | Риск coupling |

## Действия для «всё локально»
1. ...
```
