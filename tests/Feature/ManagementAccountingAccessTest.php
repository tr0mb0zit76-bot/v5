<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'payment_schedule_payment_events',
            'management_payroll_half_users',
            'management_payroll_halves',
            'management_statement_lines',
            'management_statement_imports',
            'management_expense_categories',
            'management_bank_accounts',
            'budget_opex_articles',
            'role_user',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('permissions')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
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

        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id');
            $table->foreignId('user_id');
            $table->timestamps();
        });

        Schema::create('management_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_number', 32)->unique();
            $table->string('account_mask', 16)->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('management_expense_categories', function (Blueprint $table) {
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

        Schema::create('management_statement_imports', function (Blueprint $table) {
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

        Schema::create('management_statement_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('import_id')->nullable();
            $table->foreignId('bank_account_id');
            $table->string('line_hash', 64);
            $table->date('operation_date');
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('RUB');
            $table->text('description');
            $table->string('status', 16)->default('pending');
            $table->string('source', 16)->default('import');
            $table->unsignedBigInteger('allocation_category_id')->nullable();
            $table->timestamps();
        });

        Schema::create('management_payroll_halves', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('half');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('payment_date');
            $table->string('status', 16)->default('open');
            $table->timestamps();
        });

        Schema::create('management_payroll_half_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_half_id');
            $table->foreignId('user_id');
            $table->decimal('accrued_amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payment_schedule_payment_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->string('party', 16);
            $table->decimal('amount', 14, 2);
            $table->date('payment_date');
            $table->string('transaction_reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('budget_opex_articles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('cost_type', 32)->default('fixed_monthly');
            $table->decimal('amount_monthly', 14, 2)->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function test_guest_is_redirected_from_management_accounting(): void
    {
        $this->get('/finance/management-accounting')->assertRedirect('/login');
    }

    public function test_user_without_flag_gets_forbidden(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => ['documents'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'User',
            'email' => 'user@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => false,
        ]);

        $this->actingAs($user)
            ->get('/finance/management-accounting')
            ->assertForbidden();
    }

    public function test_user_with_flag_can_open_management_accounting(): void
    {
        $role = Role::query()->create([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => ['documents'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Accountant',
            'email' => 'accountant@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
        ]);

        $this->actingAs($user)
            ->get('/finance/management-accounting')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/ManagementAccounting/Index')
                ->has('analytics')
                ->has('filters'));
    }
}
