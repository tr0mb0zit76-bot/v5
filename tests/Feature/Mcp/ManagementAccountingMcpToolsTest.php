<?php

namespace Tests\Feature\Mcp;

use App\Mcp\Servers\CrmServer;
use App\Mcp\Tools\GetManagementAccountingAnalyticsTool;
use App\Mcp\Tools\GetManagementAccountingInsightsTool;
use App\Mcp\Tools\GetUserContextTool;
use App\Mcp\Tools\ListManagementExpenseCategoriesTool;
use App\Mcp\Tools\ListManagementStatementImportsTool;
use App\Mcp\Tools\ListManagementStatementLinesTool;
use App\Mcp\Tools\RememberManagementReconcileRuleTool;
use App\Mcp\Tools\SuggestManagementStatementLineTool;
use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class ManagementAccountingMcpToolsTest extends TestCase
{
    public function test_get_user_context_exposes_management_accounting_flag(): void
    {
        $user = $this->makeManagementUser();

        $response = CrmServer::actingAs($user)->tool(GetUserContextTool::class, []);

        $response
            ->assertOk()
            ->assertSee('can_management_accounting');
    }

    public function test_management_tools_denied_without_access(): void
    {
        $user = User::query()->create([
            'name' => 'No Access',
            'email' => 'no-access@example.com',
            'password' => bcrypt('password'),
            'can_management_accounting' => false,
            'is_active' => true,
        ]);

        $response = CrmServer::actingAs($user)->tool(ListManagementExpenseCategoriesTool::class, []);

        $response->assertHasErrors();
    }

    public function test_list_categories_returns_active_articles(): void
    {
        $user = $this->makeManagementUser();

        ManagementExpenseCategory::query()->create([
            'code' => 'mcp_test_fee',
            'name' => 'MCP Test Fee',
            'kind' => 'overhead',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = CrmServer::actingAs($user)->tool(ListManagementExpenseCategoriesTool::class, []);

        $response
            ->assertOk()
            ->assertSee('MCP Test Fee');
    }

    public function test_import_lines_respect_importer_scope(): void
    {
        $owner = $this->makeManagementUser(['email' => 'owner@example.com']);
        $other = $this->makeManagementUser(['email' => 'other@example.com']);

        $bank = ManagementBankAccount::query()->create([
            'bank_name' => 'Сбер',
            'account_number' => '40702810123456789012',
            'account_mask' => '••••9012',
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bank->id,
            'format' => 'sber_registry_v1',
            'file_name' => 'june.xlsx',
            'imported_by' => $owner->id,
            'status' => 'ready',
            'lines_count' => 1,
        ]);

        ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $bank->id,
            'line_hash' => 'mcp-line-1',
            'operation_date' => '2026-06-02',
            'direction' => 'out',
            'amount' => 990,
            'description' => 'Комиссия банка',
            'status' => 'pending',
        ]);

        $ownerResponse = CrmServer::actingAs($owner)->tool(ListManagementStatementLinesTool::class, [
            'import_id' => $import->id,
        ]);

        $ownerResponse
            ->assertOk()
            ->assertSee('Комиссия банка');

        $otherResponse = CrmServer::actingAs($other)->tool(ListManagementStatementLinesTool::class, [
            'import_id' => $import->id,
        ]);

        $otherResponse->assertHasErrors();
    }

    public function test_remembered_rule_is_used_in_suggestion(): void
    {
        $user = $this->makeManagementUser();

        $category = ManagementExpenseCategory::query()
            ->where('code', 'bank_fees')
            ->firstOrFail();

        $bank = ManagementBankAccount::query()->create([
            'bank_name' => 'Сбер',
            'account_number' => '40702810987654321098',
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bank->id,
            'format' => 'sber_registry_v1',
            'file_name' => 'rules.xlsx',
            'imported_by' => $user->id,
            'status' => 'ready',
            'lines_count' => 1,
        ]);

        $line = ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $bank->id,
            'line_hash' => 'mcp-rule-line',
            'operation_date' => '2026-06-02',
            'direction' => 'out',
            'amount' => 199,
            'description' => 'Комиссия за перевод MCP-RULE-XYZ',
            'status' => 'pending',
        ]);

        CrmServer::actingAs($user)->tool(RememberManagementReconcileRuleTool::class, [
            'keyword' => 'mcp-rule-xyz',
            'direction' => 'out',
            'allocation_type' => 'category',
            'category_id' => $category->id,
        ])->assertOk();

        $suggestion = CrmServer::actingAs($user)->tool(SuggestManagementStatementLineTool::class, [
            'line_id' => $line->id,
        ]);

        $suggestion
            ->assertOk()
            ->assertSee('category')
            ->assertSee('Правило разнесения');
    }

    public function test_analytics_tool_returns_period_payload(): void
    {
        $user = $this->makeManagementUser();

        $response = CrmServer::actingAs($user)->tool(GetManagementAccountingAnalyticsTool::class, [
            'period_type' => 'month',
            'period_anchor' => '2026-06-01',
        ]);

        $response
            ->assertOk()
            ->assertSee('period_type')
            ->assertSee('totals');
    }

    public function test_insights_tool_returns_cfo_brief(): void
    {
        $user = $this->makeManagementUser();

        CrmServer::actingAs($user)->tool(GetManagementAccountingInsightsTool::class, [
            'period_type' => 'month',
            'period_anchor' => '2026-06-01',
        ])
            ->assertOk()
            ->assertSee('executive_headline')
            ->assertSee('recommendations');
    }

    public function test_list_imports_returns_owner_uploads(): void
    {
        $user = $this->makeManagementUser();

        $bank = ManagementBankAccount::query()->create([
            'bank_name' => 'Сбер',
            'account_number' => '40702810111111111111',
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        ManagementStatementImport::query()->create([
            'bank_account_id' => $bank->id,
            'format' => 'sber_registry_v1',
            'file_name' => 'imports-mcp.xlsx',
            'imported_by' => $user->id,
            'status' => 'ready',
            'lines_count' => 0,
        ]);

        $response = CrmServer::actingAs($user)->tool(ListManagementStatementImportsTool::class, []);

        $response
            ->assertOk()
            ->assertSee('imports-mcp.xlsx');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeManagementUser(array $overrides = []): User
    {
        $role = Role::query()->create([
            'name' => 'mgmt_mcp_'.uniqid(),
            'display_name' => 'Management MCP',
            'permissions' => [],
            'visibility_areas' => ['documents'],
        ]);

        return User::query()->create(array_merge([
            'role_id' => $role->id,
            'name' => 'Accountant',
            'email' => 'accountant-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
            'is_active' => true,
        ], $overrides));
    }
}
