<?php

namespace Tests\Feature;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementImport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingImportAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'management_statement_lines',
            'management_statement_imports',
            'management_expense_categories',
            'management_bank_accounts',
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
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->boolean('can_management_accounting')->default(false);
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
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('kind', 32);
            $table->boolean('is_active')->default(true);
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

        Schema::create('management_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_id')->nullable();
            $table->foreignId('bank_account_id');
            $table->unsignedInteger('row_number')->nullable();
            $table->string('line_hash', 64);
            $table->date('operation_date');
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('RUB');
            $table->text('description');
            $table->string('status', 16)->default('pending');
            $table->string('source', 16)->default('import');
            $table->timestamps();
        });
    }

    public function test_user_with_reconcile_access_can_open_import_uploaded_by_someone_else(): void
    {
        $role = Role::query()->create([
            'name' => 'accountant',
            'display_name' => 'Accountant',
            'visibility_areas' => ['finance_payment_reconcile'],
        ]);

        $owner = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $editor = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
        ]);

        $bankAccount = ManagementBankAccount::query()->create([
            'bank_name' => 'Сбер',
            'account_number' => '40702810123456789012',
            'account_mask' => '****9012',
            'currency' => 'RUB',
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bankAccount->id,
            'format' => 'bank_registry_v1',
            'file_name' => 'statement.xlsx',
            'period_from' => '2026-06-01',
            'period_to' => '2026-06-30',
            'imported_by' => $owner->id,
            'status' => 'draft',
            'lines_count' => 2,
            'lines_allocated' => 2,
        ]);

        $this->actingAs($editor)
            ->get('/finance/management-accounting/imports/'.$import->id.'?filter=allocated')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/ManagementAccounting/Reconcile')
                ->where('filters.line_filter', 'allocated'));
    }
}
