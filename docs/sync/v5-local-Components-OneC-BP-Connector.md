# Коннектор CRM ↔ 1С БП (on-prem)

> Handoff · дамп: `Exchange/CRM/1С` · эталоны Фармсервис

## MVP

Кнопка в мастере заказа (вкладка Документы) → `Document.РеализацияТоваровУслуг` (вид «Услуги»).

| CRM | 1С |
| --- | --- |
| `customer_rate` | `СуммаДокумента` / строка Услуги |
| Заказчик ИНН+КПП | поиск `Catalog.Контрагенты` |
| `orders.id` / `order_number` | допреквизиты `CRM_OrderId` / `CRM_OrderNumber` |
| Связь | таблица `order_one_c_documents` |

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
- RBAC: `OrderViewAuthorization::userCanMutateOrder`

## Позже

Счета / УПД / ЭТрН; оплаты двусторонне. Живой OData: `ONE_C_BASE_URL=https://avtoalyns-crm.case-it.ru/Avtoalians_4nYnMmRSab` (доступ по IP с прода). Поиск контрагента: `substringof(ИНН)` — `ИНН eq` в этой публикации запрещён.

## Тесты

`tests/Unit/Services/OneC/OneCRealizationMapperTest.php`  
`tests/Feature/Orders/OrderOneCRealizationTest.php`
