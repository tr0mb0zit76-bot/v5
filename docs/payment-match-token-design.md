# Токен матчинга платежей CRM ↔ банк / 1С

> Статус: каркас в коде 2026-08-11.  
> Связано: M4.1 / M4.2 в `docs/management-accounting-implementation-plan.md`, мост 1С.

## Формат

```text
CRM:{orderNumber}:{C|P}{seq}
```

Примеры: `CRM:АС-2608-0042:C1` (заказчик, транш 1), `CRM:АС-2608-0042:P2` (перевозчик, транш 2).

Назначение исходящего: `Оплата по заказу АС-… CRM:АС-…:P1`.

## Потоки

| Сторона | Кто ставит | Куда | Матчинг |
| --- | --- | --- | --- |
| Заказчик | CRM → реализация 1С | начало `Content` номенклатуры ТЭУ | входящие в УУ по токену (conf 98) |
| Перевозчик | CRM при фиксации bank_transfer | поле «Назначение» (обязательно) | исходящие в УУ по токену |

## Стоп

`PaymentMatchToken::assertOutgoingBankPurpose` — банковский платёж `party=carrier|contractor` без токена этой строки → 422.  
Env: `ONE_C_ENFORCE_OUTGOING_PAYMENT_TOKEN` (default true). Наличные/карта — без стопа.

Тот же guard использовать при будущем выпуске ПП в 1С.

## Код

- `app/Support/PaymentMatchToken.php`
- `OneCRealizationMapper` — prepend токена C1
- `ManagementAccountingMatchingService` — приоритет токена над эвристиками
- `PaymentScheduleController::recordPayment` + UI `PaymentScheduleActions`
- `FinanceOverviewService` → `payment_match_purpose` / `requires_payment_token`

## Дальше

1. При создании ПП в 1С — подставлять `purposeLine` и не проводить без токена.
2. Печатные счета/УПД — убедиться, что Content с токеном виден клиенту.
3. Backfill: подсказать токен в Reconcile для старых pending без CRM: в назначении.

См. также контур **счёта покупателю**: `docs/payment-invoice-sync-design.md`.
