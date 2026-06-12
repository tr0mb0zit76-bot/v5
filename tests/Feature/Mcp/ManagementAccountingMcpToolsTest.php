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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingMcpToolsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'management_reconcile_rules',
            'management_statement_lines',
            'management_statement_imports',
            'management_expense_categories',
            'management_bank_accounts',
            'budget_opex_articles',
            'payment_schedules',
            'orders',
            'role_user',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('permissions')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->boolean('belongs_to_management')->default(false);
            $table->boolean('can_management_accounting')->default(false);
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id');
            $table->foreignId('user_id');
            $table->timestamps();
        });

        Schema::create('management_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('bank_name');
            $table->string('account_number', 32)->unique();
            $table->string('account_mask', 16)->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('management_expense_categories', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->string('flow', 8)->default('out');
            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('include_in_budget')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('management_statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id');
            $table->string('format', 32);
            $table->string('file_name');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->foreignId('imported_by');
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('lines_count')->default(0);
            $table->unsignedInteger('lines_allocated')->default(0);
            $table->decimal('total_in', 14, 2)->default(0);
            $table->decimal('total_out', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('party', 16)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('management_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_id')->nullable();
            $table->foreignId('bank_account_id');
            $table->string('line_hash', 64);
            $table->unsignedInteger('row_number')->nullable();
            $table->date('operation_date');
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('RUB');
            $table->text('description');
            $table->string('status', 16)->default('pending');
            $table->string('match_type', 24)->nullable();
            $table->unsignedTinyInteger('match_confidence')->default(0);
            $table->string('match_notes')->nullable();
            $table->unsignedBigInteger('suggested_order_id')->nullable();
            $table->unsignedBigInteger('suggested_payment_schedule_id')->nullable();
            $table->unsignedBigInteger('suggested_category_id')->nullable();
            $table->unsignedBigInteger('suggested_user_id')->nullable();
            $table->unsignedBigInteger('allocation_category_id')->nullable();
            $table->timestamps();
        });

        Schema::create('management_reconcile_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by')->nullable();
            $table->string('keyword', 128);
            $table->string('direction', 8)->nullable();
            $table->string('allocation_type', 16);
            $table->foreignId('category_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('order_number', 32)->nullable();
            $table->unsignedBigInteger('payment_schedule_id')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->unsignedInteger('times_applied')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('budget_opex_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('cost_type', 32);
            $table->decimal('amount_monthly', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedBigInteger('management_expense_category_id')->nullable();
            $table->timestamps();
        });
    }

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

        $category = ManagementExpenseCategory::query()->create([
            'code' => 'bank_fees',
            'name' => 'Банковские комиссии',
            'kind' => 'overhead',
            'is_active' => true,
            'sort_order' => 1,
        ]);

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
