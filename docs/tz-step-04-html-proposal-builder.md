# ТЗ — Шаг 4: HTML-конструктор коммерческого предложения

**Цель:** модуль «Шаблоны КП» — WYSIWYG HTML без рассылки (как Unisender editor, без campaign); формирование КП из **лида** → preview → PDF → отправка существующим `CommercialMailService`.

**Заменяет** идею «Сколько влезет → печать» в roadmap.

---

## Отличие от текущего DOCX-потока

| Сейчас | Целевое |
|--------|---------|
| `PrintFormTemplate` + DOCX + `LeadPrintFormDraftService` | `proposal_html_templates` + HTML + рендер |
| Плейсхолдеры `${…}` в Word | Переменные `{lead.client_name}` в HTML |
| Gotenberg из DOCX | Gotenberg/Chromium HTML → PDF |

DOCX-поток **сохраняем** параллельно до миграции шаблонов.

---

## MVP scope

### Данные

```
proposal_html_templates
  id, name, slug, is_active
  html_body, css_inline nullable
  version, published_at
  owner_user_id, visibility (private|role|workspace)

proposal_html_template_variables  -- каталог, seed
```

### Backend

| # | Компонент |
|---|-----------|
| 4.1 | CRUD шаблонов (Inertia, роль settings или modules) |
| 4.2 | `LeadProposalHtmlRenderer` — snapshot лида → HTML string |
| 4.3 | `LeadProposalPdfService` — HTML → PDF (Gotenberg) |
| 4.4 | `POST leads/{lead}/proposal/from-html-template` → `lead_offers.generated_file_path` + HTML body для письма |
| 4.5 | Каталог переменных (как `PrintFormVariableCatalog` для lead) |

### Frontend

| # | Комponent |
|---|-----------|
| 4.6 | Страница `Modules/ProposalTemplates/Editor.vue` — GrapesJS или TipTap + блоки |
| 4.7 | Preview на реальном лиде |
| 4.8 | В лиде: выбор HTML-шаблона рядом с DOCX «КП из шаблона» |

### Editor (рекомендация)

- **GrapesJS** (MIT) или **Unlayer embed** — блоки, стили, mobile preview
- Экспорт: HTML + inline critical CSS
- Без SMTP в модуле — только шаблоны

---

## DoD MVP

- Руководитель собирает шаблон в редакторе.
- Менеджер на лиде выбирает шаблон → preview → PDF → «Отправить КП» (существующий mail flow).
- PHPUnit: render + PDF smoke (mock Gotenberg).

**Статус:** ✅ MVP закрыт (2026-06-21).

### Реализовано

| Компонент | Путь |
|-----------|------|
| Миграция | `proposal_html_templates`, `proposal_html_template_variables` |
| Модели | `ProposalHtmlTemplate`, `ProposalHtmlTemplateVariable` |
| Каталог переменных | `ProposalHtmlTemplateVariableCatalog` + seeder |
| Рендер | `LeadProposalHtmlRenderer` + `LeadPrintFormDraftService::buildLeadSnapshot()` |
| PDF | `LeadProposalPdfService` (Gotenberg `/forms/chromium/convert/html`) |
| CRUD | `ProposalHtmlTemplateController`, `Modules/ProposalTemplates/*` |
| Лид | `POST leads/{lead}/proposal/from-html-template`, `GET …/html-preview` |
| UI лида | блок «HTML-шаблон КП» в `LeadWizardCommercialTab.vue` |
| Почта | PDF-вложение через `CommercialMailService::resolveOfferAttachment()` |
| Тесты | `LeadProposalHtmlRendererTest`, `LeadProposalPdfServiceTest`, `LeadProposalHtmlTemplateTest` |

---

## Фазы после MVP

- Версионирование шаблонов, A/B
- Блоки «таблица маршрута», «ставка», «лого компании»
- MCP tool `list_proposal_templates` / `render_lead_proposal_preview`

---

## Оценка

4–6 недель (1 dev), MVP editor + render — 2–3 недели.
