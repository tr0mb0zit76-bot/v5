# Обогащение портрета контрагента

> Карточка · канон портрета: `docs/contractor-portrait-mvp.md` · HITL: `docs/tz-step-03-insight-drafts.md`

## Назначение

При create / по кнопке владельца CRM собирает факты (CRM + публичный веб + лёгкий снимок DaData/Checko) и предлагает **черновики** в портрет. В `contractor_portraits` пишет только accept владельца.

## Слои

| Слой | Кто пишет |
| --- | --- |
| Реквизиты | DaData + CRUD |
| Скоринг | Checko (как раньше) |
| Досье | `contractor_enrichment_runs` (overwrite snapshot) |
| Портрет | человек / HITL accept |

## Ключевые классы

- `ContractorEnrichmentService`, `ContractorCrmFactsCollector`, `ContractorWebFactsCollector`, `ContractorExternalFactsCollector`
- Job `EnrichContractorJob`
- Routes `contractors.enrichment.show|store`
- Draft sources: `crm_enrichment`, `web_public` (+ mail как раньше)

## RBAC

Владелец карточки (`owner_id`) или admin/supervisor — `ContractorPortraitAuthorization`.

## Тесты

`tests/Feature/ContractorEnrichmentTest.php`, `tests/Unit/Services/Contractor/ContractorCrmFactsCollectorTest.php`
