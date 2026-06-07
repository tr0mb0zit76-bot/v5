# Sidecar-сервисы CRM (Docker)

Контейнеры на **том же хосте**, что Laravel. Наружу — только через reverse proxy там, где нужно (Nextcloud). Gotenberg и OCR — **loopback**.

| Каталог | Сервис | Порт (local/prod) | CRM env |
|---------|--------|-------------------|---------|
| [gotenberg/](./gotenberg/) | DOCX → PDF | 3000 | `GOTENBERG_URL` |
| [ocr/](./ocr/) | Tesseract + OCRmyPDF | 3001 | `OCR_SERVICE_URL` |
| [nextcloud/](./nextcloud/) | Файловое хранилище | 8081 / 18081 | `NEXTCLOUD_BASE_URL` |
| [ntfy/](./ntfy/) | Push-уведомления (согласования) | 8092 | `NTFY_BASE_URL`, `NTFY_ENABLED` |

Подробно про OCR и intake: [docs/order-intake-ocr-service.md](../docs/order-intake-ocr-service.md).

Уведомления и ntfy: [docs/notifications-departments-ntfy.md](../docs/notifications-departments-ntfy.md) · Obsidian runbook: `Exchange/CRM/Runbooks/ntfy VPS deploy.md`
