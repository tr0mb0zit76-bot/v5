# ТЗ — Шаг 5: Скрипты продаж — фазы 5 и 6

**Источник:** `docs/scripts-module-implementation-plan.md`

**Предусловие:** накоплены `script_play_sessions` / `script_play_events` в проде.

---

## Фаза 5 — Аналитика

| # | Задача | DoD |
|---|--------|-----|
| 5.1 | `SalesScriptAnalyticsService` — агрегаты `(script_version, node_id, reaction_class)` → counts, win rate | SQL + unit test |
| 5.2 | UI отчёт: топ `reaction_class`, узлы с низкой долей progress/won | Inertia page или вкладка в Editor |
| 5.3 | Play: подсказка «по статистике чаще ведут в успех» при N ≥ 10 | Не показывать при N < порога |
| 5.4 | CSV export | Опционально |

---

## Фаза 6 — Улучшения

| # | Задача |
|---|--------|
| 6.1 | A/B две формулировки в узле |
| 6.2 | Привязка исхода сессии к `orders.id` |
| 6.3 | Контекстные теги в events (направление, тип груза) |
| 6.4 | (Долгий горизонт) NLP по комментариям |

---

## DoD фазы 5

- Руководитель на демо-данных видит «узел X + reaction price_objection → 70% lost».
- Менеджер в Play видит подсказку только при достаточном N.

**Статус:** ✅ закрыто (2026-06-21).

### Реализовано

| # | Компонент |
|---|-----------|
| 5.1 | `SalesScriptAnalyticsService` — матрица `(version, node, reaction)` + win/lost rates |
| 5.2 | `SalesScripts/Editor/Analytics.vue` + маршрут `scripts.editor.versions.analytics` |
| 5.3 | `statsHints` в Play при N ≥ `config('sales_scripts.analytics.min_sample_size')` |
| 5.4 | CSV export `scripts.editor.versions.analytics.export` |

### Фаза 6 (MVP)

| # | Статус |
|---|--------|
| 6.1 A/B формулировки | ✅ `body_variant_b`, `ab_enabled`, `ab_variant_b_weight` в редакторе + `SalesScriptNodeBodyResolver` |
| 6.2 Привязка к `orders.id` | ✅ при старте сессии и при завершении (`order_id` в complete) |
| 6.3 Контекстные теги | ✅ `context_tags` на сессии + `meta.context_tags` в events |
| 6.4 NLP комментариев | ⏸ долгий горизонт — не делали |

---

## Оценка

2–3 недели после 2–4 недель сбора данных в prod.
