<?php

namespace App\Support;

/**
 * Домены MCP-инструментов и явные кросс-доменные обращения для guard.
 */
final class McpToolDomainRegistry
{
    /**
     * @var array<string, array{domain: string, cross: list<string>}>
     */
    private const TOOLS = [
        'search_orders' => ['domain' => 'orders', 'cross' => []],
        'get_order' => ['domain' => 'orders', 'cross' => ['contractors', 'fleet']],
        'get_order_timeline' => ['domain' => 'orders', 'cross' => []],
        'list_order_documents' => ['domain' => 'orders', 'cross' => ['finance']],
        'add_order_note' => ['domain' => 'orders', 'cross' => []],
        'update_order_field' => ['domain' => 'orders', 'cross' => []],
        'update_order_route_actual' => ['domain' => 'orders', 'cross' => []],
        'get_order_intake_draft' => ['domain' => 'orders', 'cross' => ['leads']],
        'list_order_intake_drafts' => ['domain' => 'orders', 'cross' => []],
        'create_order_intake_draft_from_text' => ['domain' => 'orders', 'cross' => ['leads']],
        'search_leads' => ['domain' => 'leads', 'cross' => []],
        'search_contractors' => ['domain' => 'contractors', 'cross' => []],
        'get_contractor' => ['domain' => 'contractors', 'cross' => []],
        'create_contractor' => ['domain' => 'contractors', 'cross' => []],
        'search_tasks' => ['domain' => 'tasks', 'cross' => ['orders']],
        'get_task' => ['domain' => 'tasks', 'cross' => ['orders']],
        'create_task' => ['domain' => 'tasks', 'cross' => ['orders', 'leads']],
        'search_sales_book_articles' => ['domain' => 'sales_book', 'cross' => []],
        'get_sales_book_article' => ['domain' => 'sales_book', 'cross' => []],
        'upsert_sales_book_article' => ['domain' => 'sales_book', 'cross' => []],
        'get_sales_book_quality_insights' => ['domain' => 'sales_book', 'cross' => ['analytics']],
        'get_sales_book_quiz_insights' => ['domain' => 'sales_book', 'cross' => ['analytics']],
        'get_trainer_coaching_insights' => ['domain' => 'trainer', 'cross' => ['sales_book', 'analytics']],
        'get_manager_sales_coaching_insights' => ['domain' => 'analytics', 'cross' => ['leads']],
        'get_ai_usage_insights' => ['domain' => 'analytics', 'cross' => ['sales_book', 'trainer']],
        'get_print_form_templates_insights' => ['domain' => 'settings', 'cross' => ['orders']],
        'upsert_print_form_basic_terms' => ['domain' => 'settings', 'cross' => ['contractors']],
        'submit_contractor_print_form_change' => ['domain' => 'contractors', 'cross' => ['settings']],
        'resolve_contractor_print_form_change' => ['domain' => 'settings', 'cross' => ['contractors', 'tasks']],
        'upsert_disposition_entry' => ['domain' => 'disposition', 'cross' => ['orders']],
        'search_mail_threads' => ['domain' => 'mail', 'cross' => ['orders', 'contractors']],
        'get_mail_thread' => ['domain' => 'mail', 'cross' => ['orders', 'contractors']],
        'send_mail' => ['domain' => 'mail', 'cross' => ['orders', 'contractors']],
        'reply_mail_thread' => ['domain' => 'mail', 'cross' => ['orders', 'contractors']],
        'create_fleet_driver' => ['domain' => 'fleet', 'cross' => ['orders']],
        'create_fleet_vehicle' => ['domain' => 'fleet', 'cross' => ['orders']],
    ];

    /**
     * @return array{domain: string, cross: list<string>}|null
     */
    public static function toolConfig(string $toolName): ?array
    {
        return self::TOOLS[$toolName] ?? null;
    }

    /**
     * @return list<string>
     */
    public static function crossDomainsForTool(string $toolName): array
    {
        return self::toolConfig($toolName)['cross'] ?? [];
    }

    public static function primaryDomainForTool(string $toolName): ?string
    {
        return self::toolConfig($toolName)['domain'] ?? null;
    }

    public static function normalizedPairKey(string $domainA, string $domainB): string
    {
        return $domainA < $domainB ? "{$domainA}|{$domainB}" : "{$domainB}|{$domainA}";
    }

    /**
     * @return list<array{source_key: string, target_key: string}>
     */
    public static function crossDomainPairsForTool(string $toolName): array
    {
        $config = self::toolConfig($toolName);

        if ($config === null) {
            return [];
        }

        $primary = $config['domain'];
        $pairs = [];

        foreach ($config['cross'] as $cross) {
            if ($primary === $cross) {
                continue;
            }

            [$sourceKey, $targetKey] = $primary < $cross
                ? [$primary, $cross]
                : [$cross, $primary];

            $pairs[] = [
                'source_key' => $sourceKey,
                'target_key' => $targetKey,
            ];
        }

        return $pairs;
    }
}
