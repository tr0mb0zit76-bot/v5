# OCR sidecar (Tesseract + OCRmyPDF)

HTTP API для локального распознавания сканов и PDF без текстового слоя.

**Полная документация:** [docs/order-intake-ocr-service.md](../../docs/order-intake-ocr-service.md)

## Local

```bash
cp .env.example .env
docker compose -f docker-compose.local.yml up -d --build
curl -s http://127.0.0.1:3001/health
curl -s -F "file=@scan.pdf" http://127.0.0.1:3001/extract
curl -s -F "file=@heavy.pdf" http://127.0.0.1:3001/optimize
```

`POST /optimize` — сжатие PDF без OCR (для цепочки загрузки документов в CRM).

## Prod

**Инструкция:** [docs/order-intake-ocr-production.md](../../docs/order-intake-ocr-production.md)

```bash
cp .env.example .env
docker compose -f docker-compose.prod.yml up -d --build
curl -s http://127.0.0.1:3001/health
```

Порт по умолчанию: `127.0.0.1:3001` → контейнер `:8080`. В CRM: `ORDER_INTAKE_OCR=local`, `OCR_SERVICE_URL=http://127.0.0.1:3001`.
