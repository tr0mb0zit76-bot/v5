# Nextcloud: local + production setup

This project keeps a single, repeatable installation path for Nextcloud using Docker Compose.

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

## 7) Backups

- Database dump from `nextcloud-db-*` container.
- Volume backups:
  - `nextcloud_html_*`
  - `nextcloud_data_*`
  - `nextcloud_db_*`

Always snapshot DB and data volume in one maintenance window.
