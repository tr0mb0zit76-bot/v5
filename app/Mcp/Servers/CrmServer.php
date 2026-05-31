<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\GetContractorTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\ListOrderDocumentsTool;
use App\Mcp\Tools\SearchContractorsTool;
use App\Mcp\Tools\SearchOrdersTool;
use App\Mcp\Tools\SearchSalesBookArticlesTool;
use App\Mcp\Tools\SearchTasksTool;
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
        MCP-сервер CRM «Автоальянс»: read-only сущности + Книга продаж (upsert при sales_book_write).

        - get_user_context — роль и области видимости
        - search_orders / get_order / list_order_documents
        - search_contractors / get_contractor
        - search_tasks / get_task
        - search_sales_book_articles / upsert_sales_book_article

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
        ListOrderDocumentsTool::class,
        SearchContractorsTool::class,
        GetContractorTool::class,
        SearchTasksTool::class,
        GetTaskTool::class,
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
