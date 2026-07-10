<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\McpTokenAbilities;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class McpTokenAbilitiesTest extends TestCase
{
    public function test_default_issue_abilities_are_read_only(): void
    {
        $this->assertSame(['mcp:read'], McpTokenAbilities::defaultIssueAbilities(false));
    }

    public function test_default_issue_abilities_with_write(): void
    {
        $this->assertSame(['mcp:read', 'mcp:write'], McpTokenAbilities::defaultIssueAbilities(true));
    }

    #[DataProvider('writeToolProvider')]
    public function test_write_tools_require_write_ability(string $toolName): void
    {
        $this->assertSame(McpTokenAbilities::WRITE, McpTokenAbilities::requiredAbilityForTool($toolName));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function writeToolProvider(): array
    {
        return [
            'create_task' => ['create_task'],
            'add_order_note' => ['add_order_note'],
            'send_mail' => ['send_mail'],
        ];
    }

    public function test_read_tools_require_read_ability(): void
    {
        $this->assertSame(McpTokenAbilities::READ, McpTokenAbilities::requiredAbilityForTool('search_orders'));
        $this->assertSame(McpTokenAbilities::READ, McpTokenAbilities::requiredAbilityForTool('get_user_context'));
    }

    public function test_normalize_issue_abilities_star_is_full_access(): void
    {
        $this->assertSame(['*'], McpTokenAbilities::normalizeIssueAbilities(['*']));
    }

    public function test_normalize_issue_abilities_adds_read_when_missing(): void
    {
        $this->assertSame(['mcp:read', 'mcp:write'], McpTokenAbilities::normalizeIssueAbilities(['mcp:write'], false));
    }
}
