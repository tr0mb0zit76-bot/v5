# Распознавание заявок (OCR) на проде — инструкция

Пошаговый чеклист для сервера, где уже работает CRM (Laravel + PHP-FPM/nginx).  
OCR — **локальный Docker-сервис** на loopback; файлы заявок **не уходят** во внешние API (кроме шага структурирования через DeepSeek, если он включён).

**См. также:** [order-intake-ocr-service.md](./order-intake-ocr-service.md), [deploy/ocr/README.md](../deploy/ocr/README.md).

---

## Что должно получиться

| Компонент | Адрес | Назначение |
|-----------|--------|------------|
| CRM | `https://crm.…` | Мастер заказа → «Заполнить из заявки» |
| OCR sidecar | `http://127.0.0.1:3001` | Скан/PDF без текстового слоя → текст |
| DeepSeek API | HTTPS | Структурирование текста в JSON (если задан `DEEPSEEK_API_KEY`) |

Порт **3001** не публикуется в интернет — только `127.0.0.1` на том же хосте, что и PHP.

---

## Предварительные условия

- Docker и Docker Compose v2 на сервере CRM (или отдельной VM в той же private-сети с доступом по внутреннему IP — тогда укажите этот IP в `OCR_SERVICE_URL`).
- В `.env` CRM уже есть рабочий **`DEEPSEEK_API_KEY`** (без него intake вернёт ошибку «нужен DEEPSEEK_API_KEY»).
- Раздел **Заказы** и блок intake включены (`config/ai.php` → `order_intake.enabled`, по умолчанию `true`).
- На PHP достаточно лимитов для загрузки файлов заявки (`upload_max_filesize`, `post_max_size` — см. комментарии в `.env.example`).

---

## Шаг 1. Поднять OCR sidecar

На сервере, в каталоге проекта (или клоне репозитория с `deploy/ocr`):

```bash
cd /path/to/v5.local/deploy/ocr
cp .env.example .env
# при необходимости: OCR_PORT=3001, TZ=Europe/Samara
docker compose -f docker-compose.prod.yml up -d --build
```

Проверка с **хоста** (не из контейнера CRM):

```bash
curl -s http://127.0.0.1:3001/health
# ожидается: {"status":"ok"} или аналог

curl -s -F "file=@/path/to/test-scan.pdf" http://127.0.0.1:3001/extract | head -c 500
# ожидается JSON с полями text, method, warnings
```

Если health не отвечает:

```bash
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs --tail=80
```

Контейнер должен слушать **`127.0.0.1:3001`** (см. `docker-compose.prod.yml`).

---

## Шаг 2. Настроить `.env` CRM

В production `.env` приложения (не в `deploy/ocr/.env`):

```env
ORDER_INTAKE_OCR=local
OCR_SERVICE_URL=http://127.0.0.1:3001
OCR_SERVICE_TIMEOUT=120
```

Убедитесь, что заданы ключи для LLM-шага (если intake уже работал без OCR — обычно достаточно):

```env
DEEPSEEK_API_KEY=sk-…
```

Применить конфиг:

```bash
cd /path/to/v5.local
php artisan config:clear
php artisan config:cache   # на проде, если используете config:cache
```

---

## Шаг 3. Проверка из Laravel

Под пользователем, от которого крутится PHP (или из SSH на том же сервере):

```bash
php artisan documents:probe-ocr
```

Ожидаемый вывод:

- `ORDER_INTAKE_OCR / document_ocr.enabled: yes`
- `OCR_SERVICE_URL: http://127.0.0.1:3001`
- `OK — OCR sidecar отвечает на /health`

Дополнительно:

```bash
php artisan config:show document_ocr
php artisan config:show ai.order_intake
```

---

## Шаг 4. Проверка в интерфейсе

1. Войти в CRM под пользователем с областью **Заказы**.
2. Создать **новый заказ** (мастер).
3. Блок **«Заполнить из заявки»** → загрузить:
   - PDF с текстовым слоем, или
   - скан (JPG/PNG) / PDF без слоя (нужен работающий OCR).
4. **Распознать** → дождаться preview → **Применить к форме** → сохранить заказ.

При успехе в ответе API `POST /orders/intake/extract` в поле `extraction_method` может быть `pdf`, `docx`, `ocr`, `ocrmypdf`, `tesseract` и т.п.

---

## Шаг 5. После деплоя кода (git pull)

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:clear
php artisan route:clear
php artisan view:clear
npm ci && npm run build   # если менялся фронт
```

Перезапуск OCR **не обязателен** при обновлении только PHP/Vue — достаточно, если контейнер уже healthy.

---

## Типичные проблемы

| Симптом | Причина | Решение |
|---------|---------|---------|
| «Скан/фото: включите ORDER_INTAKE_OCR=local» | OCR выключен в `.env` | `ORDER_INTAKE_OCR=local`, `config:clear` |
| `documents:probe-ocr` → sidecar недоступен | Контейнер не запущен / другой порт | `docker compose … up -d`, проверить `curl …/health` |
| «Локальный OCR недоступен: Connection refused» | PHP не видит 127.0.0.1:3001 | OCR на другом хосте → указать внутренний URL; если PHP в Docker — сеть `host` или service name |
| Таймаут при распознавании | Большой PDF | Увеличить `OCR_SERVICE_TIMEOUT`; позже — очередь (roadmap) |
| «нужен DEEPSEEK_API_KEY» | Нет ключа для LLM | Задать `DEEPSEEK_API_KEY` (OCR только даёт текст) |
| Текст пустой, method `pdf` | Текстовый слой пустой, OCR не сработал | Логи `ocr-service-prod`, проверить `curl -F file=@… /extract` |

Логи OCR:

```bash
cd deploy/ocr
docker compose -f docker-compose.prod.yml logs -f --tail=100
```

Логи CRM (intake):

```bash
# storage/logs/laravel.log — искать order_intake_llm_failed, OCR service extract failed
```

---

## Безопасность

- Не открывайте порт **3001** в firewall наружу.
- Не проксируйте OCR через публичный nginx без auth.
- Токены MCP и DeepSeek — только в `.env`, не в git.

---

## MCP (опционально)

После распознавания в мастере в ответе есть `draft_id`. Агент (Cursor / command bar) может прочитать черновик:

- `get_order_intake_draft` — поля `wizard_patch`, предупреждения;
- `list_order_intake_drafts` — последние черновики.

Загрузка файла напрямую в MCP пока через UI мастера (`POST /orders/intake/extract`).

---

## Краткий чеклист (копировать в тикет)

- [ ] `docker compose -f deploy/ocr/docker-compose.prod.yml up -d --build`
- [ ] `curl http://127.0.0.1:3001/health` → OK
- [ ] `.env`: `ORDER_INTAKE_OCR=local`, `OCR_SERVICE_URL=http://127.0.0.1:3001`
- [ ] `DEEPSEEK_API_KEY` задан
- [ ] `php artisan config:clear` (+ `config:cache` на проде)
- [ ] `php artisan documents:probe-ocr` → OK
- [ ] Тест в мастере заказа: PDF/скан → Распознать → Применить
