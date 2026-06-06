# ntfy sidecar — push для согласований CRM

Self-hosted [ntfy](https://github.com/binwiederhier/ntfy). Laravel шлёт POST на topic пользователя (`NtfyChannel`).

## Prod (VPS)

Путь на сервере: `/var/www/www-root/data/www/avtoaliyans.ru/deploy/ntfy`

```bash
cd /var/www/www-root/data/www/avtoaliyans.ru/deploy/ntfy
cp .env.example .env
# отредактируйте NTFY_PUBLIC_URL под ваш поддомен
docker compose -f docker-compose.prod.yml up -d
curl -s http://127.0.0.1:8092/v1/health
```

Контейнер слушает только **127.0.0.1:8092**. Наружу — reverse proxy (nginx) на поддомен, например `ntfy.avtoaliyans.ru`.

В `.env` CRM (корень Laravel, не `public/`):

```env
NTFY_ENABLED=true
NTFY_BASE_URL=https://ntfy.avtoaliyans.ru
```

Push включается только для approval-событий; у пользователя должен быть заполнен `users.ntfy_topic`.

## Local

```bash
cp .env.example .env
docker compose -f docker-compose.local.yml up -d
curl -s http://127.0.0.1:8092/v1/health
```

## Проверка отправки

```bash
curl -d "тест" http://127.0.0.1:8092/my-test-topic
```

В приложении ntfy на телефоне подпишитесь на тот же topic.

## Auth (рекомендуется для prod)

Положите `server.yml` в `./etc/` (volume смонтирован в `/etc/ntfy`). См. [документацию ntfy](https://docs.ntfy.sh/config/).
