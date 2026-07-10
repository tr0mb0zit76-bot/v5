<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Redirect Domains
    |--------------------------------------------------------------------------
    |
    | These domains are the domains that OAuth clients are permitted to use
    | for redirect URIs. Each domain should be specified with its scheme
    | and host. Domains not in this list will raise validation errors.
    |
    | An "*" may be used to allow all domains.
    |
    */

    'redirect_domains' => [
        '*',
        // 'https://example.com',
        // 'http://localhost',
    ],

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Native desktop OAuth clients like Cursor and VS Code use private-use URI
    | schemes (RFC 8252) for redirect callbacks instead of standard schemes
    | like HTTPS. Here, you may list which custom schemes you will allow.
    |
    */

    'custom_schemes' => [
        // 'claude',
        // 'cursor',
        // 'vscode',
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Server
    |--------------------------------------------------------------------------
    |
    | Here you may configure the OAuth authorization server issuer identifier
    | per RFC 8414. This value appears in your protected resource and auth
    | server metadata endpoints. When null, this defaults to `url('/')`.
    |
    */

    'authorization_server' => null,

    /*
    |--------------------------------------------------------------------------
    | Локальная отладка (stdio: php artisan mcp:start crm)
    |--------------------------------------------------------------------------
    |
    | Если Bearer-токен недоступен, подставить активного пользователя по ID.
    | На проде оставьте null.
    |
    */

    'dev_user_id' => env('MCP_DEV_USER_ID'),

    /*
    |--------------------------------------------------------------------------
    | MCP Sanctum token defaults
    |--------------------------------------------------------------------------
    |
    | mcp:issue-token использует --days; здесь — подсказка для документации.
    | Глобальный потолок — config/sanctum.php expiration (SANCTUM_EXPIRATION).
    |
    */

    'token_default_days' => (int) env('MCP_TOKEN_DEFAULT_DAYS', 90),

];
