# Контур улучшений (Improvement Loop)

> Карточка кода · канон: `docs/improvement-loop-architecture.md`

## Назначение

Петля **Observe → … → Adopt** для продаж и смежных доменов. UI: **Планирование → Улучшения** (`/improvement`).

## Статус фаз

| Фаза | Суть | Статус |
| --- | --- | --- |
| L0–L3 | Сигналы sales → гипотезы → A/B → adopt/задача | ✅ |
| L4 | Рандомизация лидов + z/Wilson + next-cycle signal | ✅ |
| L5 | Домены documents/fleet/finance + HITL variant B в скрипт + MCP | ✅ |

## L5 детали

- Collect: `improvement:collect-signals --domains=sales,documents,fleet,finance`
- Adopt script: `POST improvement/adoptions/{id}/apply-script-node` → `body_variant_b` + `ab_enabled`
- MCP: `get_improvement_loop_insights` (analytics domain)

## Тесты

`php artisan test --compact tests/Feature/Improvement/` (L0–L5)
