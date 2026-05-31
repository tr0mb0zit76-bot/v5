<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\SearchOrdersTool;
use App\Mcp\Tools\SearchSalesBookArticlesTool;
use App\Mcp\Tools\UpsertSalesBookArticleTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Avtoalyans CRM')]
#[Version('0.1.0')]
#[Instructions(<<<'MARKDOWN'
        MCP-сервер CRM «Автоальянс»: заказы (read-only) и Книга продаж (чтение + upsert при sales_book_write).

        - search_orders / get_order / get_user_context — заказы и контекст пользователя
        - search_sales_book_articles — поиск страниц Книги продаж
        - upsert_sales_book_article — создать или обновить дочернюю страницу по заголовку родителя

        Аутентификация: Bearer Sanctum token.
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
        SearchSalesBookArticlesTool::class,
        UpsertSalesBookArticleTool::class,
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
