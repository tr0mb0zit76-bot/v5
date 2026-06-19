# Печатные формы: QR-проверка и DocMDP-подпись PDF

Экспериментальная защита «от неумелой правки» финального PDF заявки. **Не юридическая КЭП.**

**Последнее обновление:** 2026-06-13

---

## Цель

1. Менеджер подправил PDF в Acrobat → **цифровая подпись недействительна** (панель подписей / полоска в Reader).
2. Контрагент может **добавить своё факсимиле** (DocMDP level 2) без поломки нашей подписи.
3. На документе есть **QR + код** для сверки с эталоном в CRM (бумажные правки не ловятся crypto).

---

## Пайплайн (после согласования)

```
DOCX (черновик с QR, если в шаблоне ${document_verification_qr})
    → Gotenberg → PDF
    → [опционально] QR-штамп на каждую страницу PDF (если QR не был в DOCX)
    → [опционально] certifying signature DocMDP (PDF_CERTIFY_ENABLED=true)
    → сохранение generated_pdf_path + metadata
```

Порядок в `OrderPrintDocumentWorkflowService::persistGeneratedApprovedPdf()`:

1. `stampVerificationQr()` — если `metadata.pdf_verification_qr_in_docx` = true, PDF-штамп **не** накладывается (QR уже в DOCX).
2. `maybeCertifyApprovedPdf()` — TCPDF/FPDI + self-signed сертификат.

---

## QR и код проверки

| Компонент | Файл |
|-----------|------|
| HMAC-код документа | `app/Support/PrintFormVerificationCode.php` |
| QR в DOCX (`${document_verification_qr}`) | `OrderPrintFormDraftService::injectVerificationQrImage()` |
| QR-штамп на PDF (fallback) | `app/Services/Pdf/PdfVerificationQrStampService.php` |
| Публичная страница проверки | `GET /verify/order-documents/{orderDocument}?code=…` → `PublicOrderDocumentVerificationController` |

Плейсхолдеры в каталоге шаблонов (`PrintFormVariableCatalog`):

- `document_verification_code` — текст кода (16 символов)
- `document_verification_qr` — картинка QR (макрос в DOCX)

### Создание документа workflow

`createFromTemplate()` **сначала** создаёт `OrderDocument` (нужен `id` для кода и QR), затем генерирует DOCX с `OrderPrintFormContext.orderDocumentId` + `documentVerificationCode`.

### Metadata (`order_documents.metadata`)

| Ключ | Смысл |
|------|--------|
| `pdf_verification_code` | Код для URL и подписи под QR |
| `pdf_verification_qr` | QR включён |
| `pdf_verification_qr_in_docx` | QR вставлен в DOCX (PDF-штамп не нужен) |
| `pdf_verification_url` | URL страницы проверки |
| `pdf_verification_stamped_sha256` | Хеш PDF после QR-штампа (если штамповали на PDF) |
| `pdf_certified` | DocMDP подпись применена |
| `pdf_certified_sha256` | Хеш **после** certify (эталон для сверки) |
| `pdf_certified_docmdp` | Уровень DocMDP (по умолчанию 2) |
| `pdf_certified_at` | ISO8601 |

---

## DocMDP (config `pdf_signing.php`)

```bash
php artisan pdf-signing:generate-certificate
```

`.env`:

```
PDF_CERTIFY_ENABLED=true
# PDF_CERTIFY_DOC_MDP=2   # 2 = формы + доп. подписи; 3 = ещё аннотации
```

Зависимости: `setasign/fpdi-tcpdf`, `tecnickcom/tcpdf`.

**Ограничения (ожидаемое поведение Adobe Reader):**

- Правка текста → подпись **недействительна** (панель / полоска сверху), **не** красная плашка по всему листу.
- Визуальный appearance подписи (прямоугольник со штампом) **не дорабатываем** — ограничение PDF/Acrobat, пункт снят с roadmap.

---

## Тесты

- `tests/Unit/PdfVerificationQrStampServiceTest.php`
- `tests/Unit/PdfDocumentCertificationServiceTest.php`
- `tests/Feature/OrderDocumentVerificationPageTest.php`

---

## Связанные файлы

- `app/Services/OrderPrintDocumentWorkflowService.php` — оркестрация
- `app/Services/Pdf/PdfDocumentCertificationService.php`
- `app/Support/OrderPrintFormContext.php` — `documentVerificationCode`, `orderDocumentId`
- `resources/js/support/gridViews.js` — отдельная фича P4 (не путать с PDF)
