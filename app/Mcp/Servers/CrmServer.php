<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\SearchOrdersTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Avtoalyans CRM')]
#[Version('0.1.0')]
#[Instructions(<<<'MARKDOWN'
        MCP-сервер CRM «Автоальянс»: read-only доступ к заказам с учётом роли пользователя.

        - search_orders — поиск заказов
        - get_order — карточка заказа
        - get_user_context — роль и области видимости

        Аутентификация: Bearer Sanctum token. На проде не используйте сырые SQL — только tools.
        MARKDOWN)]
class CrmServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        GetUserContextTool::class,
        SearchOrdersTool::class,
        GetOrderTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}
