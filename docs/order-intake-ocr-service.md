# Локальный OCR для заявок (sidecar, как Gotenberg)

Живой план интеграции **Tesseract + OCRmyPDF** в контур CRM без отправки файлов во внешние API.

**Связанные документы:**

- [roadmap-2026.md](./roadmap-2026.md) — фаза 1.6 (intake заявок)
- [nextcloud-install.md](./nextcloud-install.md) — WebDAV-хранилище (OCR в NC **не** обязателен)
- [ai-platform-architecture.md](./ai-platform-architecture.md) — уровень 2/3, gate для LLM

**Статус:** ✅ `OcrServiceClient` подключён в `DocumentTextExtractor` (при `ORDER_INTAKE_OCR=local`); sidecar — `deploy/ocr/`

---

## Зачем отдельный сервис

| Сервис | Назначение | Данные наружу |
|--------|------------|---------------|
| **Gotenberg** | DOCX → PDF (предпросмотр печати) | Нет |
| **Nextcloud** | WebDAV-хранилище файлов | Нет |
| **ocr-service** | Скан/PDF без текстового слоя → plain text | Нет |

CRM вызывает OCR по **HTTP на loopback** (`127.0.0.1`), как `GOTENBERG_URL`. Структурирование JSON после OCR — отдельный шаг (шаблон / Ollama / опционально DeepSeek).

---

## Топология на сервере

```
crm.avtoaliyans.ru (Laravel + queue worker)
    │
    ├── GOTENBERG_URL=http://127.0.0.1:3000     (deploy/gotenberg)
    ├── OCR_SERVICE_URL=http://127.0.0.1:3001   (deploy/ocr)
    └── NEXTCLOUD_BASE_URL=https://nc.…         (deploy/nextcloud)
```

Все три контейнера — **только localhost**, nginx наружу не публикует порты 3000/3001.

---

## Быстрый старт (локально)

### 1. Gotenberg (если ещё не поднят)

```bash
cd deploy/gotenberg
cp .env.example .env
docker compose -f docker-compose.local.yml up -d
curl -s http://127.0.0.1:3000/health
```

В `.env` CRM:

```env
DOC_PREVIEW_DRIVER=gotenberg
GOTENBERG_URL=http://127.0.0.1:3000
```

### 2. OCR service

```bash
cd deploy/ocr
cp .env.example .env
docker compose -f docker-compose.local.yml up -d --build
curl -s http://127.0.0.1:3001/health
```

Пробный запрос:

```bash
curl -s -F "file=@/path/to/scan.pdf" http://127.0.0.1:3001/extract
```

Ответ JSON:

```json
{
  "text": "…",
  "method": "ocrmypdf",
  "warnings": []
}
```

### 3. CRM (.env) — после подключения клиента

```env
ORDER_INTAKE_OCR=local
OCR_SERVICE_URL=http://127.0.0.1:3001
OCR_SERVICE_TIMEOUT=120
# ORDER_INTAKE_STRUCTURE=deepseek   # template | ollama | deepseek
```

---

## Прод

**Пошаговая инструкция:** [order-intake-ocr-production.md](./order-intake-ocr-production.md)

Кратко:

```bash
cd deploy/ocr
cp .env.example .env
docker compose -f docker-compose.prod.yml up -d --build
curl -s http://127.0.0.1:3001/health
```

В `.env` CRM: `ORDER_INTAKE_OCR=local`, `OCR_SERVICE_URL=http://127.0.0.1:3001`, затем `php artisan documents:probe-ocr`.

---

## API ocr-service (v0)

| Метод | Путь | Описание |
|-------|------|----------|
| GET | `/health` | `{ "status": "ok" }` |
| POST | `/extract` | `multipart/form-data`, поле `file` |

**PDF:**

1. `pdftotext` (если есть текстовый слой) → `method: pdf_text`
2. иначе `ocrmypdf --force-ocr --language rus+eng` → `pdftotext` → `method: ocrmypdf`

**Изображения** (jpg/png/webp): `tesseract … -l rus+eng` → `method: tesseract`

**DOCX:** не OCR — в CRM уже есть `DocumentTextExtractor::extractDocx()`.

---

## Laravel: `OcrServiceClient`

Каркас: `app/Services/Documents/OcrServiceClient.php` (по аналогии с `DocxPdfPreviewService`).

```php
// Планируемая цепочка в DocumentTextExtractor:
// 1) текстовый слой / DOCX (как сейчас)
// 2) если text === '' && config('document_ocr.enabled'):
//        OcrServiceClient::extractFromPath($path, $extension)
// 3) OrderDocumentIntakeService::structureWithLlm($text)
```

Регистрация в `AppServiceProvider` — singleton, как `NextcloudWebDavStorage`.

Конфиг: `config/document_ocr.php` + ключи в `.env.example`.

---

## Очередь (рекомендуется для prod)

OCR на больших PDF может занимать 30–120 с. Не держать HTTP-запрос мастера:

1. `POST /orders/intake/extract` → сохранить файл, dispatch `ExtractOrderIntakeTextJob`
2. Job: OCR → LLM → обновить `order_intake_drafts`
3. UI: polling / Inertia deferred props «Статус распознавания»

На первом этапе можно оставить синхронный вызов для PDF ≤ N страниц.

---

## Чеклист интеграции

- [x] Поднять `deploy/ocr` локально, `curl /extract` на тестовом скане
- [x] Подключить `OcrServiceClient` в `DocumentTextExtractor`
- [x] `config/document_ocr.php`, env, `php artisan documents:probe-ocr`
- [ ] Feature test: HTTP OCR → intake extract (mock)
- [x] Roadmap 1.6.1: OCR sidecar
- [ ] (опц.) Queue job + статус в UI мастера
- [ ] (опц.) `ORDER_INTAKE_STRUCTURE=template` для типовых заявок без DeepSeek

---

## Nextcloud OCR — когда имеет смысл

Приложения **Recognize** / **Workflow OCR** в NC — альтернатива, если **все** документы живут в NC и команда уже админит NC-приложения. Для блока «Заполнить из заявку» в мастере проще **прямой ocr-service** (меньше кругов CRM→NC→CRM).

---

## Оценка трудозатрат (остаток)

| Задача | Часы |
|--------|------|
| Подключение клиента + extractor | 8–12 |
| Artisan probe + тесты | 4–6 |
| Queue + UI статуса | 8–12 |
| **Итого до prod OCR intake** | **~20–30 ч** |

---

## История

| Дата | Изменение |
|------|-----------|
| 2026-06-01 | Каркас `deploy/ocr`, документ, `OcrServiceClient` stub |
