<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AddOrderNoteTool;
use App\Mcp\Tools\AllocateManagementStatementLineTool;
use App\Mcp\Tools\ApplyOrderWizardDraftTool;
use App\Mcp\Tools\CreateContractorTool;
use App\Mcp\Tools\CreateFleetDriverTool;
use App\Mcp\Tools\CreateFleetVehicleTool;
use App\Mcp\Tools\CreateOrderIntakeDraftFromTextTool;
use App\Mcp\Tools\CreateTaskTool;
use App\Mcp\Tools\ExtractOrderDraftFromDocumentTool;
use App\Mcp\Tools\GetAiUsageInsightsTool;
use App\Mcp\Tools\GetContractorTool;
use App\Mcp\Tools\GetHeadOfSalesInsightsTool;
use App\Mcp\Tools\GetMailSyncStatusTool;
use App\Mcp\Tools\GetMailThreadTool;
use App\Mcp\Tools\GetManagementAccountingAnalyticsTool;
use App\Mcp\Tools\GetManagementAccountingInsightsTool;
use App\Mcp\Tools\GetManagerSalesCoachingInsightsTool;
use App\Mcp\Tools\GetOrderFieldLexiconTool;
use App\Mcp\Tools\GetOrderIntakeDraftTool;
use App\Mcp\Tools\GetOrderTimelineTool;
use App\Mcp\Tools\GetOrderTool;
use App\Mcp\Tools\GetPrintFormBasicTermsTool;
use App\Mcp\Tools\GetPrintFormTemplatesInsightsTool;
use App\Mcp\Tools\GetSalesBookArticleTool;
use App\Mcp\Tools\GetSalesBookQualityInsightsTool;
use App\Mcp\Tools\GetSalesBookQuizInsightsTool;
use App\Mcp\Tools\GetSalesScriptCoachingInsightsTool;
use App\Mcp\Tools\GetSalesScriptGraphTool;
use App\Mcp\Tools\GetTaskTool;
use App\Mcp\Tools\GetTrainerCoachingInsightsTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\ListManagementExpenseCategoriesTool;
use App\Mcp\Tools\ListManagementReconcileRulesTool;
use App\Mcp\Tools\ListManagementStatementImportsTool;
use App\Mcp\Tools\ListManagementStatementLinesTool;
use App\Mcp\Tools\ListOrderDocumentsTool;
use App\Mcp\Tools\ListOrderIntakeDraftsTool;
use App\Mcp\Tools\ListSalesScriptsTool;
use App\Mcp\Tools\RememberManagementReconcileRuleTool;
use App\Mcp\Tools\RememberOrderIntakePhraseTool;
use App\Mcp\Tools\ReplyMailThreadTool;
use App\Mcp\Tools\ResolveContractorPrintFormChangeTool;
use App\Mcp\Tools\SearchContractorsTool;
use App\Mcp\Tools\SearchMailThreadsTool;
use App\Mcp\Tools\SearchOrdersTool;
use App\Mcp\Tools\SearchSalesBookArticlesTool;
use App\Mcp\Tools\SearchTasksTool;
use App\Mcp\Tools\SendMailTool;
use App\Mcp\Tools\SubmitContractorPrintFormChangeTool;
use App\Mcp\Tools\SuggestManagementStatementLineTool;
use App\Mcp\Tools\UpdateOrderFieldTool;
use App\Mcp\Tools\UpdateOrderRouteActualTool;
use App\Mcp\Tools\UpsertDispositionEntryTool;
use App\Mcp\Tools\UpsertPrintFormBasicTermsTool;
use App\Mcp\Tools\UpsertSalesBookArticleTool;
use App\Mcp\Tools\ValidateSalesScriptGraphTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Avtoalyans CRM')]
#[Version('0.2.0')]
#[Instructions(<<<'MARKDOWN'
        MCP-сервер CRM «Автоальянс»: чтение сущностей, запись задач и диспозиции, Книга продаж.

        - get_user_context — роль и области видимости
        - search_orders / get_order / get_order_timeline / list_order_documents
        - get_order_field_lexicon — русские названия полей и синонимы
        - search_contractors / get_contractor / create_contractor
        - create_fleet_driver / create_fleet_vehicle — водитель и авто (модалки в заказе)
        - search_tasks / get_task / create_task
        - add_order_note — заметка в ленту заказа
        - update_order_field — одно поле заказа (whitelist)
        - update_order_route_actual — фактическая погрузка/выгрузка
        - upsert_disposition_entry — ячейка диспозиции (утро/вечер)
        - search_sales_book_articles / get_sales_book_article / upsert_sales_book_article / get_sales_book_quality_insights / get_sales_book_quiz_insights
        - get_ai_usage_insights — аналитика обращений к AI (admin / settings_system)
        - get_trainer_coaching_insights — зацикливание и коучинг в тренажёре (аналитика тренажёра / settings_system)
        - get_sales_script_coaching_insights — живые прохождения скриптов: исходы, возражения, слабые менеджеры, рекомендации (аналитика тренажёра / settings_system)
        - list_sales_scripts / get_sales_script_graph / validate_sales_script_graph — структура, тексты и диагностика графов скриптов
        - get_manager_sales_coaching_insights — Outcome Intelligence по лидам (область leads / settings_system)
        - get_head_of_sales_insights — сводка для руководителя продаж: команда, воронка, скрипты (supervisor / reports)
        - get_order_intake_draft / list_order_intake_drafts / create_order_intake_draft_from_text / extract_order_draft_from_document / apply_order_wizard_draft / remember_order_intake_phrase — черновики заявок
        - apply_order_wizard_draft: dry_run=true → confirm_token, затем вызов с confirm_token (создание заказа из draft_id)
        - После create/extract в ответе draft_id и wizard_path. Альтернатива UI: apply_order_wizard_draft после dry_run.
        - get_print_form_basic_terms — общие пункты базовых условий cp/dp (заказчик/перевозчик) из настроек CRM
        - get_print_form_templates_insights — шаблоны DOCX, базовые условия и диагностика печати (settings_system / Юрик)
        - upsert_print_form_basic_terms — прямое сохранение базовых условий (admin / settings_system)
        - submit_contractor_print_form_change — заявка на согласование условий контрагента (менеджер / Юрик)
        - resolve_contractor_print_form_change — утверждение/отклонение заявки (руководитель)
        - search_mail_threads / get_mail_thread / get_mail_sync_status / send_mail / reply_mail_thread — переписка, IMAP sync и отправка из CRM
        - Управленческий учёт (can_management_accounting / admin):
          list_management_statement_imports, list_management_statement_lines, suggest_management_statement_line,
          allocate_management_statement_line (remember_keyword — обучение правила), get_management_accounting_analytics,
          get_management_accounting_insights,
          list_management_expense_categories, remember_management_reconcile_rule, list_management_reconcile_rules

        Аутентификация: Bearer Sanctum token (`php artisan mcp:issue-token {user} --write` для записи; по умолчанию только mcp:read). Ротация: перевыпуск ~90 дней.
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
        CreateContractorTool::class,
        CreateFleetDriverTool::class,
        CreateFleetVehicleTool::class,
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
        GetSalesScriptCoachingInsightsTool::class,
        ListSalesScriptsTool::class,
        GetSalesScriptGraphTool::class,
        ValidateSalesScriptGraphTool::class,
        GetManagerSalesCoachingInsightsTool::class,
        GetHeadOfSalesInsightsTool::class,
        GetPrintFormBasicTermsTool::class,
        GetPrintFormTemplatesInsightsTool::class,
        UpsertPrintFormBasicTermsTool::class,
        SubmitContractorPrintFormChangeTool::class,
        ResolveContractorPrintFormChangeTool::class,
        GetOrderIntakeDraftTool::class,
        ListOrderIntakeDraftsTool::class,
        CreateOrderIntakeDraftFromTextTool::class,
        ExtractOrderDraftFromDocumentTool::class,
        ApplyOrderWizardDraftTool::class,
        RememberOrderIntakePhraseTool::class,
        SearchMailThreadsTool::class,
        GetMailThreadTool::class,
        GetMailSyncStatusTool::class,
        SendMailTool::class,
        ReplyMailThreadTool::class,
        ListManagementStatementImportsTool::class,
        ListManagementStatementLinesTool::class,
        SuggestManagementStatementLineTool::class,
        AllocateManagementStatementLineTool::class,
        GetManagementAccountingAnalyticsTool::class,
        GetManagementAccountingInsightsTool::class,
        ListManagementExpenseCategoriesTool::class,
        RememberManagementReconcileRuleTool::class,
        ListManagementReconcileRulesTool::class,
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
