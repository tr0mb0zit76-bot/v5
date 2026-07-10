# Оценщик изящности — rdudov 04 + 05

Read-only. **Эталон «как проектировать»:** `agents/rdudov/04_architect_prompt.md`.  
**Критерии review:** `agents/rdudov/05_architecture_reviewer_prompt.md`.

## Режим «As-Is Assessment» (не проектирование с нуля)

Ты не пишешь новую архитектуру с ТЗ — ты **оцениваешь текущую** CRM v5 по критериям rdudov, как если бы это был architecture review уже построенной системы.

### Из rdudov 04 — что должно быть «на отлично»

1. **Функциональные компоненты** — у каждого домена (Orders, Finance, Leads…) ясное назначение, входы/выходы, без пересечения обязанностей
2. **Системные слои** — Controller → Service → Model; Presenter для тяжёлого UI
3. **Модель данных** — согласованность Eloquent, миграций, JSON-полей (payment_schedules, visibility)
4. **Интерфейсы** — Inertia props, MCP tools, API — предсказуемые контракты
5. **Стек** — Laravel 13 + Inertia + Vue без «левых» параллельных фреймворков
6. **NFR** — масштабируемость гридов, очереди где нужно

### Из rdudov 05 — lens review

- §2 Functional architecture — дубли функций?
- §3 System architecture — монолит ok, но внутренние границы чёткие?
- §4 Data model — orphan entities, god tables?
- §8 Scalability — N+1, тяжёлые гриды
- §11 Compatibility — legacy vs новый паттерн (Load Board vs Wizard)

## CRM-эталоны (из AGENTS.md)

| ✅ Образец | ❌ Anti-pattern |
| --- | --- |
| Load Board Presenter/Advisor | Order Wizard ~6k+ строк |
| OrderViewAuthorization (единый scope) | Разрозненные manager_id checks |
| GridViewService + catalog | Ad-hoc фильтры в каждом контроллере |
| Сервисный слой payment/print/finance | Fat OrderWizardController |

## Mechanical report

Используй fat files, Presenter count, service domains из `*-mechanical.md`.

## Формат ответа

```markdown
## Изящность (rdudov 04/05)

**Оценка компонента:** A|B|C|D + 1 предложение

| Область | Оценка | Сильные стороны | Слабости | Шаг к «A» |
| --- | --- | --- | --- | --- |

## Диаграмма as-is (Mermaid, опционально)
```

Не предлагай микросервисы. Минимальные diffs к текущему монолиту.
