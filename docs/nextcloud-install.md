# Nextcloud: local + production setup

This project keeps a single, repeatable installation path for Nextcloud using Docker Compose.

**См. также:** [deploy/README.md](../deploy/README.md) (Gotenberg, OCR sidecar), [order-intake-ocr-service.md](./order-intake-ocr-service.md).

## 1) Prerequisites

- Docker Engine + Docker Compose plugin installed.
- DNS/hosts already points `nc.avtoaliyans.local` (dev) or `nc.avtoaliyans.ru` (prod):
  - Local: `127.0.0.1 nc.avtoaliyans.local`
  - Production: public DNS `A`/`AAAA` for `nc.avtoaliyans.ru` → IP сервера.
- TCP 80/443 open on production for reverse proxy.

## 2) Prepare environment file

From `deploy/nextcloud` copy `.env.example` to `.env` and set strong passwords:

```bash
cp .env.example .env
```

Mandatory values:

- `NEXTCLOUD_DOMAIN` (example: `nc.avtoaliyans.local` or prod `nc.avtoaliyans.ru`)
- `NEXTCLOUD_TRUSTED_DOMAINS` (same hostname or multiple, comma-separated)
- `NEXTCLOUD_ADMIN_USER`, `NEXTCLOUD_ADMIN_PASSWORD`
- `NEXTCLOUD_DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `REDIS_PASSWORD`

For local:

- `NEXTCLOUD_PORT=8081`

For production:

- `NEXTCLOUD_PORT=18081` (internal bind for reverse proxy)
- `NEXTCLOUD_TRUSTED_PROXIES=127.0.0.1`

## 3) Local install

Run from `deploy/nextcloud`:

```bash
docker compose --env-file .env -f docker-compose.local.yml up -d
```

Then open:

- `http://localhost:8081` or
- `http://nc.avtoaliyans.local:8081`

If you want clean local URL without port (`http://nc.avtoaliyans.local`), set reverse proxy in OSPanel/Nginx to `http://127.0.0.1:8081`.

## 4) Production install (VDS)

Copy `deploy/nextcloud` folder to server, then:

```bash
docker compose --env-file .env -f docker-compose.prod.yml up -d
```

This exposes Nextcloud only on loopback `127.0.0.1:${NEXTCLOUD_PORT}`.
Publish externally through ISPmanager site config (Nginx/Apache reverse proxy).

### Nginx reverse-proxy example

```nginx
server {
    listen 80;
    server_name nc.avtoaliyans.ru;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name nc.avtoaliyans.ru;

    # SSL paths from ISPmanager/certbot
    ssl_certificate     /path/to/fullchain.pem;
    ssl_certificate_key /path/to/privkey.pem;

    client_max_body_size 2048M;

    location / {
        proxy_pass http://127.0.0.1:18081;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto https;
        proxy_read_timeout 3600;
    }
}
```

## 5) Post-install hardening

Run once after first boot:

```bash
docker exec -u www-data nextcloud-app-prod php occ maintenance:repair
docker exec -u www-data nextcloud-app-prod php occ db:add-missing-indices
docker exec -u www-data nextcloud-app-prod php occ db:convert-filecache-bigint
```

Enable cron mode:

```bash
docker exec -u www-data nextcloud-app-prod php occ background:cron
```

Then add host cron job (every 5 min):

```bash
*/5 * * * * docker exec -u www-data nextcloud-app-prod php -f /var/www/html/cron.php
```

## 6) Смена домена (например перенос на `nc.avtoaliyans.ru`)

Nextcloud и CRM на одном сервере, но **разные имена хостов** — нормальная схема: CRM ходит в хранилище по HTTPS на поддомене файлов.

1. **DNS** — запись `nc.avtoaliyans.ru` → IP сервера (как у CRM/витрины).
2. **TLS** — сертификат на `nc.avtoaliyans.ru` в конфиге reverse proxy (Nginx/Apache) перед контейнером.
3. **`deploy/nextcloud/.env`** (и переменные compose):
   - `NEXTCLOUD_DOMAIN=nc.avtoaliyans.ru`
   - `NEXTCLOUD_TRUSTED_DOMAINS=nc.avtoaliyans.ru` (несколько хостов — через запятую в одной строке, как ожидает образ)
   - Перезапуск стека: `docker compose --env-file .env -f docker-compose.prod.yml up -d`  
     У вас уже задан `OVERWRITEHOST` из `NEXTCLOUD_DOMAIN` — ссылки внутри Nextcloud должны строиться с нового хоста.
4. **Trusted domains внутри Nextcloud** (если после смены видите «Untrusted domain»):

   ```bash
   docker exec -u www-data nextcloud-app-prod php occ trusted_domains:add nc.avtoaliyans.ru
   ```

   Старый хост при необходимости удалите: `docker exec -u www-data nextcloud-app-prod php occ trusted_domains:list` и `trusted_domains:remove` (см. `occ trusted_domains:remove --help`).
5. **Публичный URL для CLI и фоновых задач** (по желанию, если что-то ссылается на старый адрес):

   ```bash
   docker exec -u www-data nextcloud-app-prod php occ config:system:set overwrite.cli.url --value="https://nc.avtoaliyans.ru/"
   ```

6. **Reverse proxy** — обновить `server_name` и прокси на тот же `127.0.0.1:${NEXTCLOUD_PORT}`; заголовки `X-Forwarded-Proto`, `Host` оставить как в примере выше.
7. **Laravel (CRM)** — в `.env` продакшена:

   ```env
   DOCUMENT_STORAGE=nextcloud
   NEXTCLOUD_BASE_URL=https://nc.avtoaliyans.ru
   ```

   Учётка `NEXTCLOUD_WEBDAV_*` без смены домена обычно не меняется. Затем `php artisan config:clear` (или `config:cache`).
8. **Старый домен** — по возможности редирект 301 со старого `server_name` на `https://nc.avtoaliyans.ru$request_uri`, чтобы не ломались закладки.

Файлы на диске контейнера (volumes) при смене только имени хоста **переносить не нужно** — меняются DNS, прокси и конфиг Nextcloud.

## 7) Устранение неполадок

### ISPmanager: `cannot load certificate ... nc.avtoaliyans.ru_le1.crtca` (nginx test failed)

После перевыпуска или замены SSL панель не сохранила `/etc/nginx/vhosts/www-root/nc.avtoaliyans.ru.conf`: в конфиге остались пути к **старому** Let's Encrypt, файла цепочки `.crtca` на диске нет. Nginx не перезагружается — сайт `nc.*` перестаёт проксировать на Docker.

1. На сервере посмотреть, какие файлы сертификата реально есть:

   ```bash
   ls -la /var/www/httpd-cert/www-root/nc.avtoaliyans.ru*
   ```

2. В **ISPmanager** → сайт `nc.avtoaliyans.ru` → **SSL-сертификаты** → заново выпустить Let's Encrypt (или привязать загруженный сертификат). Дождаться успешного сохранения без ошибки «Тест конфигурации веб-сервера … неудачно».

3. Проверить nginx и перезагрузить:

   ```bash
   nginx -t && systemctl reload nginx
   ```

4. Убедиться, что в vhost снова есть прокси на `http://127.0.0.1:18081` (раздел 4 ниже).

Предупреждение `listen ... http2` deprecated — не блокирует запуск; позже можно заменить на `listen 443 ssl;` и отдельно `http2 on;`.

### В браузере заглушка Reg.ru («Сайт только что создан»)

Это **не** Nextcloud: запросы на `nc.avtoaliyans.ru` обслуживает дефолтный сайт панели (ISPmanager / Reg.ru), а не reverse proxy на контейнер.

Порядок действий на VDS:

1. Поднять стек Nextcloud:

   ```bash
   cd /path/to/deploy/nextcloud
   docker compose --env-file .env -f docker-compose.prod.yml up -d
   docker compose --env-file .env -f docker-compose.prod.yml ps
   ```

   Должны быть `running`: `nextcloud-app-prod`, `nextcloud-db-prod`, `nextcloud-redis-prod`.

2. Проверить, что приложение слушает локально (порт из `.env`, обычно `18081`):

   ```bash
   curl -sS -o /dev/null -w "%{http_code}\n" http://127.0.0.1:18081/status.php
   ```

   Ожидается `200` и JSON с `"installed":true`.

3. В ISPmanager для сайта `nc.avtoaliyans.ru` включить **обратный прокси** на `http://127.0.0.1:18081` (не «статический сайт» / не корень Reg.ru). Пример nginx — в разделе 4 выше. После смены SSL-сертификата конфиг прокси иногда сбрасывается — его нужно восстановить.

4. Открыть `https://nc.avtoaliyans.ru/` — должен быть экран входа Nextcloud, не заглушка.

### CRM: «Не удалось создать корневую директорию Nextcloud (/CRM), HTTP 404»

Пока в браузере заглушка Reg.ru, WebDAV тоже получает 404 с того же хоста — сначала исправьте пункты выше.

Когда Nextcloud в браузере открывается:

- В `.env` CRM проверьте:

  ```env
  DOCUMENT_STORAGE=nextcloud
  NEXTCLOUD_BASE_URL=https://nc.avtoaliyans.ru
  NEXTCLOUD_WEBDAV_USER=crm-bot
  NEXTCLOUD_WEBDAV_PASSWORD=...
  NEXTCLOUD_WEBDAV_ROOT=/remote.php/dav/files/crm-bot/CRM
  ```

  Пользователь `crm-bot` должен существовать в Nextcloud (или укажите существующего в `NEXTCLOUD_WEBDAV_USER` и скорректируйте путь после `files/`).

- Диагностика с сервера CRM:

  ```bash
  php artisan config:clear
  php artisan documents:probe-nextcloud
  ```

## 8) Backups

- Database dump from `nextcloud-db-*` container.
- Volume backups:
  - `nextcloud_html_*`
  - `nextcloud_data_*`
  - `nextcloud_db_*`

Always snapshot DB and data volume in one maintenance window.
