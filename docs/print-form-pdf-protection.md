# Печатные формы: QR-проверка и DocMDP-подпись PDF

Экспериментальная защита «от неумелой правки» финального PDF заявки. **Не юридическая КЭП.**

**Последнее обновление:** 2026-07-20

---

## Цель

1. Менеджер подправил PDF в Acrobat → **цифровая подпись недействительна** (панель подписей / полоска в Reader).
2. Контрагент может **добавить своё факсимиле** (DocMDP level 2) без поломки нашей подписи.
3. На документе есть **QR + код** для сверки с эталоном в CRM (бумажные правки не ловятся crypto) — **только если в шаблоне есть плейсхолдер**.

---

## Пайплайн (после согласования)

```
DOCX (QR только при ${document_verification_qr} в шаблоне)
    → Gotenberg → PDF
    → QR-штамп на PDF — только если QR уже был в DOCX
      (или PRINT_VERIFICATION_QR_PDF_FALLBACK=true — старое «всегда штамповать»)
    → [опционально] certifying signature DocMDP (PDF_CERTIFY_ENABLED=true)
    → сохранение generated_pdf_path + metadata
```

Порядок в `OrderPrintDocumentWorkflowService::persistGeneratedApprovedPdf()`:

1. `stampVerificationQr()` — при `pdf_verification_qr_in_docx` штамп не дублируется; без макроса в шаблоне и без fallback — QR на PDF не ставится.
2. `maybeCertifyApprovedPdf()` — TCPDF/FPDI + self-signed сертификат.

---

## QR и код проверки

| Компонент | Файл |
|-----------|------|
| HMAC-код документа (на каждый `OrderDocument`) | `app/Support/PrintFormVerificationCode.php` |
| Размеры QR (DOCX / PDF / PNG) | `app/Support/PrintFormVerificationQrDimensions.php`, `config/documents.php` → `verification_qr` |
| QR в DOCX (`${document_verification_qr}`) | `OrderPrintFormDraftService::injectVerificationQrImage()` |
| Исключение QR из VML-смещений подписи/печати | `DocxVmlOverlayStylePatcher` + `countVerificationQrVmlShapes()` |
| QR-штамп на PDF (fallback) | `app/Services/Pdf/PdfVerificationQrStampService.php` |
| Публичная страница проверки | `GET /verify/order-documents/{orderDocument}?code=…` → `PublicOrderDocumentVerificationController` |
| Область видимости контрагента на странице | `app/Support/PrintVerificationPageScope.php` |

Плейсхолдеры в каталоге шаблонов (`PrintFormVariableCatalog`):

- `document_verification_code` — текст кода (16 символов)
- `document_verification_qr` — картинка QR (макрос в DOCX)

### Отдельный QR на каждую заявку (заказчик / перевозчик)

У заказчика и перевозчика — **разные** записи `order_documents` → разные `id`, коды и URL. Один QR **не** обслуживает обе стороны.

При создании workflow в `metadata` пишется `party`: `customer` | `carrier` (из `OrderPrintFormContext.printParty` или типа шаблона).

Публичная страница `/verify/...` показывает контрагента **только своей** стороны:

| `metadata.party` | На странице проверки |
|------------------|----------------------|
| `customer` | только заказчик |
| `carrier` | только перевозчик (`carrier_contractor_id` при нескольких перевозчиках) |
| не задан | номер заявки и хеш, без имён контрагентов |

Страница **без авторизации**; доступ по `?code=…`. Внутренние подсказки про сверку хеша на странице не выводятся.

### Создание документа workflow

`createFromTemplate()` **сначала** создаёт `OrderDocument` (нужен `id` для кода и QR), затем генерирует DOCX с `OrderPrintFormContext.orderDocumentId` + `documentVerificationCode`.

### Предпросмотр шаблона (мастер / настройки)

Без реального `OrderDocument` QR рисуется через `OrderPrintFormContext::forTemplatePreview($orderId)` — чтобы не оставался сырой `${document_verification_qr}`. Ссылка в QR для предпросмотра не ведёт на боевой документ.

### Размеры QR (config)

`config/documents.php` → `verification_qr`, переопределение в `.env`:

```env
PRINT_VERIFICATION_QR_DOCX_PX=80      # сторона в DOCX (PhpWord), по умолчанию 80
PRINT_VERIFICATION_QR_PDF_MM=12       # штамп на PDF (мм), по умолчанию 12
PRINT_VERIFICATION_QR_PNG_PIXEL=5     # плотность PNG при генерации
```

Минимумы в коде: 48 px / 8 мм / 3 px. Крупный QR в ячейке таблицы DOCX раздувает строки на многостраничных формах — уменьшайте `DOCX_PX`, если ломается сетка подписи/печати.

### Metadata (`order_documents.metadata`)

| Ключ | Смысл |
|------|--------|
| `party` | `customer` / `carrier` — для страницы проверки и контекста печати |
| `carrier_contractor_id` | Перевозчик слота (если не основной `order.carrier_id`) |
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
- `tests/Unit/PrintFormVerificationQrDimensionsTest.php`
- `tests/Unit/DocxVmlOverlayStylePatcherTest.php`
- `tests/Unit/PrintVerificationPageScopeTest.php`
- `tests/Feature/OrderDocumentVerificationPageTest.php`

---

## Связанные файлы

- `app/Services/OrderPrintDocumentWorkflowService.php` — оркестрация
- `app/Services/Pdf/PdfDocumentCertificationService.php`
- `app/Support/OrderPrintFormContext.php` — `documentVerificationCode`, `orderDocumentId`, `forTemplatePreview()`
- `resources/js/support/gridViews.js` — отдельная фича P4 (не путать с PDF)
