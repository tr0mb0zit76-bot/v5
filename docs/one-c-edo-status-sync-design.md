# Sync статусов ЭДО из 1С → чек-лист closing / сроки оплаты

**Статус:** фаза A (MVP, исходящие заказчику) · **2026-08-12**

## Цель

Забирать из 1С факт **отправки** закрывающих документов заказчику по ЭДО и писать в уже существующую таблицу `order_document_edo_acknowledgements`, чтобы:

- закрывался слот closing в чек-листе (без скана);
- `paymentPackageAttachedAt(customer)` получал дату → пересчёт `payment_schedules.planned_date`.

## Источник в 1С (проверено на БП Автоальянс)

| Сущность | Роль |
| --- | --- |
| `order_one_c_documents` (CRM) | `external_ref` реализации |
| `InformationRegister_ОбъектыУчетаДокументовЭДО` | связь `ОбъектУчета` (реализация / СФ) ↔ `ЭлектронныйДокумент` |
| `Document_ЭлектронныйДокументИсходящийЭДО` | `ДатаОтправки`, `НомерДокумента`, `ТипРегламента` |
| `InformationRegister_СостоянияДокументовЭДО` | `Состояние` (`ОбменЗавершен`, `ОжидаетсяПодтверждение`, …) |
| `Document_СчетФактураВыданный` | доп. объект учёта (тот же ЭДО часто висит и на СФ) |

Фильтр OData: `ОбъектУчета eq '{guid-as-string}'` (сравнение с `guid'…'` на этом поле падает).

## Алгоритм (фаза A)

1. Курсор по реализациям `status=created`.
2. `base_url` из `request_payload` / publication catalog.
3. Ссылки ЭДО по реализации (+ по СФ с `ДокументОснование` = реализация).
4. Исходящий ЭДО «отправлен», если есть `ДатаОтправки` **или** состояние ∈ `config('one_c.edo_sync.sent_states')`.
5. Upsert ack: `party=customer`; тип = `upd` (УПД / `ЭтоУниверсальныйДокумент`) иначе `invoice_factura` + `act`.
6. Ручные отметки (`confirmed_by` заполнен) **не затираются**.

## Команда / cron

```bash
php artisan one-c:sync-edo-status [--limit=]
```

Расписание: `hourly` (`routes/console.php`).

## Не в scope фазы A

- Входящие от перевозчиков (`Document_ЭлектронныйДокументВходящийЭДО`) — нужна связь заказ ↔ поступление.
- Прямая интеграция с оператором ЭДО (Астрал/Калуга) в обход 1С.

## Код

- `OneCEdoStatusSyncService`
- `OneCBpClient::findEdoLinksForAccountingObject` / `getOutgoingEdoDocument` / `getEdoDocumentState`
- `OrderDocumentEdoAcknowledgementService::upsertFromOneC`
- Тест: `tests/Feature/OneC/OneCEdoStatusSyncServiceTest.php`
