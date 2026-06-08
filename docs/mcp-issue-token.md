# MCP-токен для Cursor

Краткая инструкция: как выпустить Bearer-токен для `v5-crm-local` / `v5-crm-prod`.

## Локально

```bash
php scripts/ensure-cursor-user.php
php artisan mcp:issue-token --user-id=<id из ensure-cursor-user>
```

Скопируйте токен в `~/.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "v5-crm-local": {
      "url": "http://v5.local/mcp/crm",
      "headers": {
        "Authorization": "Bearer <token>"
      }
    }
  }
}
```

## Prod

**Что значит «зафиксировать prod URL»:** в репозитории и в `.env.example` записан канонический адрес endpoint (`https://crm.avtoaliyans.ru/mcp/crm`). Вам нужно один раз прописать этот URL и Bearer-токен в `~/.cursor/mcp.json` на ПК — **секрет только токен**, не URL. После смены домена обновите URL в Cursor и в комментарии `.env.example`.

```bash
cd /var/www/www-root/data/www/avtoaliyans.ru
php artisan mcp:issue-token --user-id=<ваш user id>
```

В `~/.cursor/mcp.json` на рабочей машине:

```json
"v5-crm-prod": {
  "url": "https://crm.avtoaliyans.ru/mcp/crm",
  "headers": {
    "Authorization": "Bearer <token>"
  }
}
```

## Безопасность

- Токен = права пользователя CRM (области видимости, роль).
- Не коммитьте токен в git.
- Админский токен не раздавайте менеджерам.
- Отзыв: удалите personal access token в БД / перевыпустите.

## Проверка

В Cursor: MCP → `v5-crm-*` → tool `get_user_context`.
