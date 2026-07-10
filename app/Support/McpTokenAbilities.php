<?php

namespace App\Support;

/**
 * Sanctum-способности для MCP-токенов (personal access tokens).
 */
final class McpTokenAbilities
{
    public const READ = 'mcp:read';

    public const WRITE = 'mcp:write';

    public const FULL = '*';

    /**
     * Инструменты, изменяющие данные CRM (требуют {@see self::WRITE}).
     *
     * @var list<string>
     */
    private const WRITE_TOOLS = [
        'add_order_note',
        'allocate_management_statement_line',
        'apply_order_wizard_draft',
        'create_contractor',
        'create_fleet_driver',
        'create_fleet_vehicle',
        'create_order_intake_draft_from_text',
        'create_task',
        'extract_order_draft_from_document',
        'remember_management_reconcile_rule',
        'remember_order_intake_phrase',
        'reply_mail_thread',
        'resolve_contractor_print_form_change',
        'send_mail',
        'submit_contractor_print_form_change',
        'update_order_field',
        'update_order_route_actual',
        'upsert_disposition_entry',
        'upsert_print_form_basic_terms',
        'upsert_sales_book_article',
    ];

    /**
     * @return list<string>
     */
    public static function defaultIssueAbilities(bool $withWrite = false): array
    {
        if ($withWrite) {
            return [self::READ, self::WRITE];
        }

        return [self::READ];
    }

    public static function requiredAbilityForTool(string $toolName): string
    {
        return in_array($toolName, self::WRITE_TOOLS, true) ? self::WRITE : self::READ;
    }

    public static function isWriteTool(string $toolName): bool
    {
        return self::requiredAbilityForTool($toolName) === self::WRITE;
    }

    /**
     * @param  list<string>  $abilities
     * @return list<string>
     */
    public static function normalizeIssueAbilities(array $abilities, bool $withWrite = false): array
    {
        if ($abilities === []) {
            return self::defaultIssueAbilities($withWrite);
        }

        if (in_array(self::FULL, $abilities, true)) {
            return [self::FULL];
        }

        $normalized = array_values(array_unique(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $abilities,
        ))));

        if ($withWrite && ! in_array(self::WRITE, $normalized, true)) {
            $normalized[] = self::WRITE;
        }

        if (! in_array(self::READ, $normalized, true) && ! in_array(self::FULL, $normalized, true)) {
            array_unshift($normalized, self::READ);
        }

        return $normalized;
    }
}
