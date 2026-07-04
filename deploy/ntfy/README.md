# ntfy sidecar — снят с поддержки

Push-уведомления CRM переведены на **Firebase Cloud Messaging** (мобильное APK).

Документация: [docs/notifications-departments-ntfy.md](../../docs/notifications-departments-ntfy.md).

Контейнер на проде можно остановить:

```bash
cd deploy/ntfy
docker compose -f docker-compose.prod.yml down
```

Удалите из `.env` CRM переменные `NTFY_ENABLED`, `NTFY_BASE_URL`.
