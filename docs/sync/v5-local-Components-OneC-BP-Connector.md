# Коннектор CRM ↔ 1С БП (on-prem)

> Handoff · дамп: `Exchange/CRM/1С` · эталоны Фармсервис

## MVP

Кнопка в мастере заказа (вкладка Документы) → `Document.РеализацияТоваровУслуг` (вид «Услуги»).

| CRM | 1С |
| --- | --- |
| `customer_rate` | `СуммаДокумента` / строка Услуги |
| `customer_payment_form` (ставка НДС) | `Услуги.СтавкаНДС` (`НДС22` / `БезНДС`…), `СуммаНДС`, `СуммаВключаетНДС`, `ДокументБезНДС`; метка формы в `Комментарий` |
| Заказчик ИНН+КПП | поиск `Catalog.Контрагенты` (`substringof`); **create**, если нет — при реализации и при смене заказчика в мастере (`EnsureOneCOrderCustomerJob`). ИНН 12 → `ФизическоеЛицо` + `ИндивидуальныйПредприниматель`; ИНН 10 → `ЮридическоеЛицо` + КПП. При находке ИП, созданного как организация — PATCH типа |

| Сводка (тело) | `Услуги.Содержание` = тело сводки без префикса `CRM:…`; номенклатура **ТЭУ** |

| `orders.id` / номер | `Комментарий` (`CRM {number} (id N)`); допреквизитов в ИБ нет |
| Организация | `own_company_id` → публикация по ИНН (`OneCPublicationCatalog::forOrder`); иначе default Autalliance |


| Связь | таблица `order_one_c_documents` |

**Push CRM → 1С:** `pushForOrder` — создать / PATCH обновить / no-op. **Posted=true** → запрет изменения из CRM. UI: «Создать…» / «Обновить данные в 1С» / disabled «актуально» / blocked при проведении.

## Эталоны

| CRM id | Номер | Сумма | 1С реализация (dump) |
| --- | --- | --- | --- |
| 19 | АС-ТД-107 | 95 000 | 0000-000016 |
| 36 | АС-ТД-213 | 290 000 | 0000-000041 |
| 86 | АС-ТД-486 | 330 000 | 0000-000053 |

Контрагент dump: ООО ФАРМСЕРВИС, ИНН `2312178145`, КПП `231201001`.

## Код

- `config/one_c.php` — `ONE_C_ENABLED`, `ONE_C_DRIVER=fake|http`
- `OneCRealizationMapper`, `OneCBpClient`, `OneCRealizationSyncService`
- `POST orders/{order}/one-c/realization` (`orders.one-c.realization.store`)
- RBAC: `RoleAccess::canCreateOneCRealization` (admin / clerk / accountant) + `OrderViewAuthorization::userCanMutateOrder`.

## Удаление / аварийная очистка

- При **удалении заказа** в CRM: если есть `order_one_c_documents` с `external_ref` — OData GET; при `Posted=false` → **пометка удаления** (`DeletionMark`) в 1С; при `Posted=true` → заказ **не** удаляется.
- Команда: `php artisan one-c:delete-realization {ref}` (непроведённые / сироты / смоук).
- Жёсткий OData DELETE у учётки `Odata` на этой ИБ падает на правах последовательностей — используем пометку.

## Позже

Счета / УПД / ЭТрН; оплаты двусторонне. Живой OData: `ONE_C_BASE_URL=https://avtoalyns-crm.case-it.ru/Avtoalians_4nYnMmRSab` (доступ по IP с прода). Поиск контрагента: `substringof(ИНН)` — `ИНН eq` в этой публикации запрещён.

Мульти-ИБ (публикации на `avtoalyns-crm.case-it.ru`, общие `Odata`/`1codata`):

| Компания | Publication | Org Ref (проверено) |
| --- | --- | --- |
| Автоальянс | `Avtoalians_4nYnMmRSab` | `19b37fca-5d84-11f1-8bf4-fa163ea037a3` |
| Гросс | `Gross_44N8sTPEXf` | `13d87b6e-bae2-11ef-89a3-dc68443ee9e4` (ИНН 6345031755) |
| Профсфера | `ProSfera_gRLXXFMK8M` | `68778110-58ca-11f1-8af0-fa163eafb81d` (ИНН 6321213940) |

На `avtoalyns.case-it.ru` с прода — 401 openresty. У Гросс/Профсфера фильтр `Date` в OData может падать с AUTOORDER — обход в клиенте.

**Счета / матчинг:** реализация → `СчетНаОплатуПокупателю` → sync `one-c:sync-invoice-numbers` (12ч) → `orders`/`payment_schedules.invoice_number`. Дизайн: `docs/payment-invoice-sync-design.md`. Исходящие — токен `CRM:…` (`docs/payment-match-token-design.md`).

**ЭДО (фаза A, исходящие заказчику):** `one-c:sync-edo-status` (hourly) → `ОбъектыУчетаДокументовЭДО` → исходящий ЭДО → upsert `order_document_edo_acknowledgements` (не затирает ручные). Дизайн: `docs/one-c-edo-status-sync-design.md`. Входящие от перевозчиков — позже.

**Агент-контролёр моста** (код): `OneCPublicationCatalog`, `OneCBridgeHealthService`, `OneCBridgeCheckService`, `OneCBridgeEscalationService`; команды `one-c:bridge-check`, `pull-one-c-bank --company=`; виджет `OneCBridgeStatusWidget`; remember в Reconcile; автосоздание контрагента в `OneCBpClient::createRealization`. Дизайн: `docs/one-c-bridge-control-agent-design.md`. Env: `ONE_C_BRIDGE_ESCALATION_USER_ID`.

## Тесты

`tests/Unit/Services/OneC/OneCRealizationMapperTest.php`  
`tests/Unit/Services/OneC/OneCBpClientDeleteRealizationTest.php`  
`tests/Feature/Orders/OrderOneCRealizationTest.php`  
`tests/Feature/Orders/OrderDeletionOneCCleanupTest.php`  
`tests/Feature/OneC/OneCEdoStatusSyncServiceTest.php`  
`tests/Unit/Services/OneC/OneCInvoiceNumberSyncServiceTest.php`
