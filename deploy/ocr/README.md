# OCR sidecar (Tesseract + OCRmyPDF)

HTTP API для локального распознавания сканов и PDF без текстового слоя.

**Полная документация:** [docs/order-intake-ocr-service.md](../../docs/order-intake-ocr-service.md)

## Local

```bash
cp .env.example .env
docker compose -f docker-compose.local.yml up -d --build
curl -s http://127.0.0.1:3001/health
curl -s -F "file=@scan.pdf" http://127.0.0.1:3001/extract
```

## Prod

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Порт по умолчанию: `127.0.0.1:3001` → контейнер `:8080`.
