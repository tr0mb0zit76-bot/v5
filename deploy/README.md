# Sidecar-сервисы CRM (Docker)

Контейнеры на **том же хосте**, что Laravel. Наружу — только через reverse proxy там, где нужно (Nextcloud). Gotenberg и OCR — **loopback**.

Часовой пояс sidecar и диспозиции по умолчанию: **`Europe/Samara`** (`TZ` в `deploy/*/.env`, `APP_TIMEZONE` / `DISPOSITION_TIMEZONE` в CRM `.env`).

| Каталог | Сервис | Порт (local/prod) | CRM env |
|---------|--------|-------------------|---------|
| [gotenberg/](./gotenberg/) | DOCX → PDF | 3000 | `GOTENBERG_URL` |
| [ocr/](./ocr/) | Tesseract + OCRmyPDF | 3001 | `OCR_SERVICE_URL` |
| [nextcloud/](./nextcloud/) | Файловое хранилище | 8081 / 18081 | `NEXTCLOUD_BASE_URL` |

Push на телефон — **Firebase (FCM)**, см. [docs/notifications-departments-ntfy.md](../docs/notifications-departments-ntfy.md). Каталог [ntfy/](./ntfy/) — **снят с поддержки**, контейнер можно остановить.

Подробно про OCR и intake: [docs/order-intake-ocr-service.md](../docs/order-intake-ocr-service.md).
