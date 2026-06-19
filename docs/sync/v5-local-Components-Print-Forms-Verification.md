# Print Forms — QR verification

> Компонент: публичная проверка целостности PDF заявок по QR.  
> Подробно: git `docs/print-form-pdf-protection.md`

## Сущность

- **Один QR = один `OrderDocument`** (заявка заказчика и заявка перевозчика — разные id и коды).
- QR в DOCX: `${document_verification_qr}`; fallback-штамп на PDF, если макроса нет в шаблоне.
- Страница проверки: `/verify/order-documents/{id}?code=…` — **без login**, throttle 60/min.

## Ключевые классы

| Класс | Роль |
|-------|------|
| `PrintFormVerificationCode` | HMAC-код 16 символов |
| `PrintFormVerificationQrDimensions` | Размеры QR (`config/documents.php`) |
| `OrderPrintFormDraftService` | Вставка QR, подсчёт VML для патча |
| `DocxVmlOverlayStylePatcher` | Смещения подписи/печати; QR из цепочки исключён |
| `PublicOrderDocumentVerificationController` | HTML страница проверки |
| `PrintVerificationPageScope` | `party` → какой контрагент показать |

## Metadata

- `party`: `customer` | `carrier`
- `pdf_verification_code`, `pdf_verification_url`, `pdf_certified_sha256`

## Конфиг (.env)

```
PRINT_VERIFICATION_QR_DOCX_PX=80
PRINT_VERIFICATION_QR_PDF_MM=12
PRINT_VERIFICATION_QR_PNG_PIXEL=5
```

## Деплой

После правок: `git pull`, `php artisan optimize:clear`. Старые DOCX/PDF не пересобираются сами — «Пересоздать черновик» или повторное согласование.

*Обновлено: 2026-06-20.*
