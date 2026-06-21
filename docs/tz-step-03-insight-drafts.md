# ТЗ — Шаг 3: HITL инсайты в портрет

**Цель:** из письма/заметки LLM предлагает факты → менеджер **принимает/отклоняет** → запись в `contractor_portraits`.

---

## Схема

```sql
contractor_insight_drafts
  id, contractor_id
  field_key          -- success_criteria | price_sensitivity | ...
  proposed_value     json/text
  source_type        -- mail_message | interaction | lead
  source_id
  confidence         decimal nullable
  status             pending | accepted | rejected
  reviewed_by, reviewed_at
  created_at
```

---

## DoD

| # | Задача |
|---|--------|
| 3.1 | Миграция + модель + policy |
| 3.2 | `ContractorInsightDraftService::extractFromMailMessage()` — LLM + whitelist полей |
| 3.3 | UI блок «Предложения» на вкладке «Портрет» |
| 3.4 | accept → merge через `ContractorPortraitService`; reject → status |
| 3.5 | Ledger-событие при accept |
| 3.6 | Feature-тест HITL flow | ✅ `ContractorInsightDraftTest` |

**Шаг 3 закрыт (2026-06-21).** UI «Предложения» на вкладке «Портрет»; accept/reject API; ledger `portrait_insight_accepted`.

---

## Whitelist полей (v1)

- `success_criteria`, `preferred_channel`, `price_sensitivity`, `typical_objections[]`, `internal_notes` (append)

**Запрещено автозаполнение:** ИНН, телефоны без подтверждения (политика ПД — см. `ai-platform-architecture.md`).

---

## Зависимости

- Шаг 2 (контекст письма)
- Шаг 1 (портрет MVP)
