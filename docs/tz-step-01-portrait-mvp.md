# ТЗ — Шаг 1: Портрет клиента + коучинг (MVP)

**Связано:** `contractor-portrait-mvp.md`, commercial-intelligence roadmap §3.7, §3.5.

**Цель:** менеджер за 2 минуты фиксирует «как работать с клиентом»; ассистент видит структурированный контекст; на «Лидах» — баннер неполноты и коучинг.

---

## Уже в коде (не дублировать)

- Таблицы `contractor_portraits`, расширения contacts/interactions
- `ContractorPortraitService`, вкладка «Портрет», modal «итог контакта»
- Merge из interaction → portrait
- `get_contractor` + `portrait_context` (MCP)
- Баннер неполноты в `Leads/Wizard.vue` (< 50% coverage)
- `LeadSalesCoachingPanel` на `Leads/Index.vue`

---

## Sprint 1 (текущий) — DoD

| # | Задача | Критерий |
|---|--------|----------|
| 1.1 | `mergeFromLead()` + preview | need → success_criteria; authority/budget/timeline → слоты без overwrite |
| 1.2 | API `GET/POST leads/{lead}/portrait-merge` | JSON preview + apply; права как у лида |
| 1.3 | Кнопка «Перенести в портрет» на вкладке квалификации лида | Видна при counterparty + заполненной квалификации |
| 1.4 | На вкладке «Портрет» — последние 5 interactions + «Зафиксировать итог» | Без перехода на «Коммуникации» |
| 1.5 | Feature-тесты merge из лида + coverage в serializeLead | PHPUnit green |

**Sprint 1 закрыт (2026-06-21).** Реализовано: merge API, кнопка в лиде, interactions на вкладке «Портрет».

---

## Sprint 2 (следующий)

| # | Задача |
|---|--------|
| 2.1 | Цепочки почты на вкладке «Портрет» (после стабильного mail sync) |
| 2.2 | `ContractorContextBuilder`: orders summary + mail threads |
| 2.3 | Merge diff preview в modal interaction |
| 2.4 | Статья Книги: чеклист полей портрета (ссылка из баннера) |
| 2.5 | Коучинг в wizard лида (опционально; на Index уже есть) |

---

## Правила merge из лида

- **Не перезаписывать** заполненные enum-слоты (≠ `unknown`) и непустой текст.
- **need** → `success_criteria` (если пусто).
- **authority** → строка в `internal_notes` с префиксом «ЛПР: …» (если нет дубля).
- **budget** → эвристика `price_sensitivity` + при необходимости note.
- **timeline** → эвристика `decision_cadence` + note «Срок: …».
- После merge — пересчёт `coverage_pct`.

---

## API

```
GET  /leads/{lead}/portrait-merge/preview  → { proposed: {...}, skipped: [...] }
POST /leads/{lead}/portrait-merge           → { portrait: {...}, flash message }
```

---

## Тесты

- `tests/Feature/LeadPortraitMergeTest.php`
- `tests/Unit/ContractorPortraitLeadMergeTest.php` (эвристики)
