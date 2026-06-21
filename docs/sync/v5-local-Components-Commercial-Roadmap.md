---
id: component-commercial-roadmap
type: component
status: canon
name: "Commercial Roadmap (steps 1–5)"
componentType: domain
tags: [leads, contractors, mail, scripts, proposals]
---

# Commercial Roadmap — шаги 1–5 (2026-06-21)

Живой сводный документ. Детали по шагам: `docs/tz-step-*.md`, оглавление: `docs/commercial-roadmap-implementation-tz.md`.

**Git:** ветка `feature/commercial-roadmap-steps-1-5` · коммит `09b920f` (merged в `master` локально; на GitHub — feature-ветка + PR).

---

## Шаг 1 — Портрет из лида

| Что | Где |
| --- | --- |
| Сервис | `app/Services/Commercial/ContractorPortraitService.php` — `mergeFromLead()`, preview |
| Маршруты | `leads.portrait-merge`, `leads.portrait-merge.preview` |
| UI | `Leads/Wizard.vue`, `ContractorPortraitTab.vue` |
| Тест | `tests/Feature/LeadPortraitMergeTest.php` |

---

## Шаг 2 — Агент «Почта»

| Что | Где |
| --- | --- |
| Персона | `pochta` в `config/ai_agents.php` |
| Сервис | `app/Services/Commercial/MailThreadAnalysisService.php` |
| Tools | `AgentToolRegistry` — анализ цепочки, черновик ответа |
| Тесты | `tests/Unit/Services/Commercial/MailThreadAnalysisServiceTest.php`, `tests/Unit/Services/Agents/AgentToolRegistryMailAnalysisTest.php` |

---

## Шаг 3 — HITL insight drafts

| Что | Где |
| --- | --- |
| Миграция | `2026_06_21_222758_create_contractor_insight_drafts_table.php` |
| Модель / сервис | `ContractorInsightDraft`, `ContractorInsightDraftService` |
| API | `ContractorInsightDraftController`, policy |
| UI | блок в `ContractorPortraitTab.vue` |
| Тест | `tests/Feature/ContractorInsightDraftTest.php` |

---

## Шаг 4 — HTML-конструктор КП

| Что | Где |
| --- | --- |
| Миграции | `proposal_html_templates`, `proposal_html_template_variables` |
| Рендер / PDF | `LeadProposalHtmlRenderer`, `LeadProposalPdfService` (Gotenberg chromium) |
| CRUD шаблонов | `ProposalHtmlTemplateController`, `Modules/ProposalTemplates/*` |
| Маршруты | `modules.proposal-templates.*`, `leads.proposal.from-html-template`, `leads.proposal.html-preview` |
| UI лида | `LeadWizardCommercialTab.vue` |
| Seeder | `ProposalHtmlTemplateVariableSeeder` |
| Тесты | `LeadProposalHtmlRendererTest`, `LeadProposalPdfServiceTest`, `LeadProposalHtmlTemplateTest.php` |

**Прод:** `DOC_PREVIEW_DRIVER=gotenberg`, `GOTENBERG_URL=…` · после deploy: `php artisan db:seed --class=ProposalHtmlTemplateVariableSeeder`

---

## Шаг 5 — Аналитика скриптов (фазы 5 + 6.1–6.3)

| Что | Где |
| --- | --- |
| Config | `config/sales_scripts.php` — `min_sample_size` (10), окно 30 дней |
| Сервис | `SalesScriptAnalyticsService` — матрица, drop-off, CSV |
| Миграция | `2026_06_21_240000_add_ab_and_context_to_sales_scripts.php` |
| A/B / контекст | `SalesScriptNodeBodyResolver`, `SalesScriptPlayContextResolver` |
| UI | `SalesScripts/Editor/Analytics.vue`, hints в `Play.vue`, A/B в `Graph.vue` |
| Маршруты | `scripts.editor.versions.analytics`, `.analytics.export` |
| Тесты | `SalesScriptAnalyticsServiceTest.php`, `SalesScriptAnalyticsPageTest.php` |

---

## Тесты (локально)

```powershell
cd C:\OSPanel\home\v5.local
# Надёжно — по файлам (избегает конфликта schemaDropMany vs RefreshDatabase):
php artisan test --compact tests/Feature/LeadPortraitMergeTest.php
php artisan test --compact tests/Feature/ContractorInsightDraftTest.php
php artisan test --compact tests/Feature/LeadProposalHtmlTemplateTest.php
php artisan test --compact tests/Unit/Services/Commercial/MailThreadAnalysisServiceTest.php
php artisan test --compact tests/Unit/Services/Agents/AgentToolRegistryMailAnalysisTest.php
php artisan test --compact tests/Unit/Services/Commercial/LeadProposalHtmlRendererTest.php
php artisan test --compact tests/Unit/Services/Commercial/LeadProposalPdfServiceTest.php
php artisan test --compact tests/Unit/Services/SalesScripts/SalesScriptAnalyticsServiceTest.php
php artisan test --compact tests/Feature/SalesScripts/SalesScriptAnalyticsPageTest.php
```

**БД тестов:** `.env.testing` → `DB_HOST=127.0.1.21`, `DB_DATABASE=u_tromb`. Не задавать `DB_HOST` в PowerShell. При рассинхроне: `php artisan migrate:fresh --env=testing --force`.

---

## Что дальше (backlog)

- Merge PR `feature/commercial-roadmap-steps-1-5` → `master`, deploy + migrate на прод
- Фаза 6.4 NLP по комментариям Play — не делали
- Публикация пользовательских инструкций в Книгу (если появятся)
- Накопление Play-сессий на проде для осмысленной аналитики (N ≥ 10)

*Обновлено: 2026-06-21.*
