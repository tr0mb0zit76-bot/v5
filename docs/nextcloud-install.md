# Nextcloud: local + production setup

This project keeps a single, repeatable installation path for Nextcloud using Docker Compose.

## 1) Prerequisites

- Docker Engine + Docker Compose plugin installed.
- DNS/hosts already points `nc.log-sol.local`:
  - Local: `127.0.0.1 nc.log-sol.local`
  - Production: public DNS `A` record to your VDS IP.
- TCP 80/443 open on production for reverse proxy.

## 2) Prepare environment file

From `deploy/nextcloud` copy `.env.example` to `.env` and set strong passwords:

```bash
cp .env.example .env
```

Mandatory values:

- `NEXTCLOUD_DOMAIN` (example: `nc.log-sol.local`)
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
- `http://nc.log-sol.local:8081`

If you want clean local URL without port (`http://nc.log-sol.local`), set reverse proxy in OSPanel/Nginx to `http://127.0.0.1:8081`.

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
    server_name nc.log-sol.local;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name nc.log-sol.local;

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

## 6) Backups

- Database dump from `nextcloud-db-*` container.
- Volume backups:
  - `nextcloud_html_*`
  - `nextcloud_data_*`
  - `nextcloud_db_*`

Always snapshot DB and data volume in one maintenance window.
