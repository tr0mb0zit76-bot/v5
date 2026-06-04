<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddOrderNoteTool;
use App\Mcp\Tools\CreateOrderIntakeDraftFromTextTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\GetAiUsageInsightsTool;
use App\Mcp\Tools\GetContractorTool;
use App\Mcp\Tools\GetMailSyncStatusTool;
use App\Mcp\Tools\GetMailThreadTool;
use App\Mcp\Tools\GetManagerSalesCoachingInsightsTool;
use App\Mcp\Tools\GetOrderFieldLexiconTool;
use App\Mcp\Tools\GetOrderIntakeDraftTool;
use App\Mcp\Tools\GetOrderTimelineTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetSalesBookArticleTool;
use App\Mcp\Tools\GetSalesBookQualityInsightsTool;
use App\Mcp\Tools\GetSalesBookQuizInsightsTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\GetTrainerCoachingInsightsTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\ListOrderDocumentsTool;
use App\Mcp\Tools\ListOrderIntakeDraftsTool;
use App\Mcp\Tools\RememberOrderIntakePhraseTool;
use App\Mcp\Tools\SearchContractorsTool;
use App\Mcp\Tools\SearchMailThreadsTool;
use App\Mcp\Tools\SearchOrdersTool;
use App\Mcp\Tools\SearchSalesBookArticlesTool;
use App\Mcp\Tools\SearchTasksTool;
use App\Mcp\Tools\UpdateOrderFieldTool;
use App\Mcp\Tools\UpdateOrderRouteActualTool;
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
        - get_order_field_lexicon — русские названия полей и синонимы
        - search_contractors / get_contractor
        - search_tasks / get_task / create_task
        - add_order_note — заметка в ленту заказа
        - update_order_field — одно поле заказа (whitelist)
        - update_order_route_actual — фактическая погрузка/выгрузка
        - upsert_disposition_entry — ячейка диспозиции (утро/вечер)
        - search_sales_book_articles / get_sales_book_article / upsert_sales_book_article / get_sales_book_quality_insights / get_sales_book_quiz_insights
        - get_ai_usage_insights — аналитика обращений к AI (admin / settings_system)
        - get_trainer_coaching_insights — зацикливание и коучинг в тренажёре (аналитика тренажёра / settings_system)
        - get_manager_sales_coaching_insights — Outcome Intelligence по лидам (область leads / settings_system)
        - get_order_intake_draft / list_order_intake_drafts / create_order_intake_draft_from_text / remember_order_intake_phrase — черновики заявок и обучение фразам из диалога
        - После create_order_intake_draft_from_text в ответе есть draft_id и wizard_path (/orders/create?intake_draft=…). MCP не открывает UI — пользователь переходит по wizard_path (command bar в CRM делает это сам через navigate_to).
        - search_mail_threads / get_mail_thread / get_mail_sync_status — переписка и ошибки IMAP sync

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
        GetOrderFieldLexiconTool::class,
        GetOrderTimelineTool::class,
        ListOrderDocumentsTool::class,
        SearchContractorsTool::class,
        GetContractorTool::class,
        SearchTasksTool::class,
        GetTaskTool::class,
        CreateTaskTool::class,
        AddOrderNoteTool::class,
        UpdateOrderFieldTool::class,
        UpdateOrderRouteActualTool::class,
        UpsertDispositionEntryTool::class,
        SearchSalesBookArticlesTool::class,
        GetSalesBookArticleTool::class,
        UpsertSalesBookArticleTool::class,
        GetSalesBookQualityInsightsTool::class,
        GetSalesBookQuizInsightsTool::class,
        GetAiUsageInsightsTool::class,
        GetTrainerCoachingInsightsTool::class,
        GetManagerSalesCoachingInsightsTool::class,
        GetOrderIntakeDraftTool::class,
        ListOrderIntakeDraftsTool::class,
        CreateOrderIntakeDraftFromTextTool::class,
        RememberOrderIntakePhraseTool::class,
        SearchMailThreadsTool::class,
        GetMailThreadTool::class,
        GetMailSyncStatusTool::class,
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
