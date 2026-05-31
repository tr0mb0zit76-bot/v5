<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddOrderNoteTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\GetContractorTool;
use App\Mcp\Tools\GetOrderTimelineTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\ListOrderDocumentsTool;
use App\Mcp\Tools\SearchContractorsTool;
use App\Mcp\Tools\SearchOrdersTool;
use App\Mcp\Tools\SearchSalesBookArticlesTool;
use App\Mcp\Tools\SearchTasksTool;
use App\Mcp\Tools\UpdateOrderFieldTool;
use App\Mcp\Tools\UpsertDispositionEntryTool;
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
        MCP-сервер CRM «Автоальянс»: чтение сущностей, запись задач и диспозиции, Книга продаж.

        - get_user_context — роль и области видимости
        - search_orders / get_order / get_order_timeline / list_order_documents
        - search_contractors / get_contractor
        - search_tasks / get_task / create_task
        - add_order_note — заметка в ленту заказа
        - update_order_field — одно поле заказа (whitelist)
        - upsert_disposition_entry — ячейка диспозиции (утро/вечер)
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
        GetOrderTimelineTool::class,
        ListOrderDocumentsTool::class,
        SearchContractorsTool::class,
        GetContractorTool::class,
        SearchTasksTool::class,
        GetTaskTool::class,
        CreateTaskTool::class,
        AddOrderNoteTool::class,
        UpdateOrderFieldTool::class,
        UpsertDispositionEntryTool::class,
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
