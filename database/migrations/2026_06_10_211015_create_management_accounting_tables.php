<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'can_management_accounting')) {
            Schema::table('users', function (Blueprint $table): void {
                if (Schema::hasColumn('users', 'belongs_to_management')) {
                    $table->boolean('can_management_accounting')
                        ->default(false)
                        ->after('belongs_to_management');
                } else {
                    $table->boolean('can_management_accounting')->default(false);
                }
            });
        }

        if (! Schema::hasTable('management_bank_accounts')) {
            Schema::create('management_bank_accounts', function (Blueprint $table): void {
                $table->id();
                $table->string('bank_name');
                $table->string('account_number', 32);
                $table->string('account_mask', 16)->nullable();
                $table->string('currency', 3)->default('RUB');
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->unique('account_number');
            });
        }

        if (! Schema::hasTable('management_expense_categories')) {
            Schema::create('management_expense_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 64)->unique();
                $table->string('name');
                $table->string('kind', 32);
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('management_statement_imports')) {
            Schema::create('management_statement_imports', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('bank_account_id')->constrained('management_bank_accounts')->cascadeOnDelete();
                $table->string('format', 32)->default('sber_registry_v1');
                $table->string('file_name');
                $table->date('period_from')->nullable();
                $table->date('period_to')->nullable();
                $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
                $table->string('status', 16)->default('draft');
                $table->unsignedInteger('lines_count')->default(0);
                $table->unsignedInteger('lines_allocated')->default(0);
                $table->decimal('total_in', 14, 2)->default(0);
                $table->decimal('total_out', 14, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('management_statement_lines')) {
            Schema::create('management_statement_lines', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('import_id')
                    ->nullable()
                    ->constrained('management_statement_imports', indexName: 'mgmt_stmt_line_import_fk')
                    ->nullOnDelete();
                $table->foreignId('bank_account_id')
                    ->constrained('management_bank_accounts', indexName: 'mgmt_stmt_line_bank_acct_fk')
                    ->cascadeOnDelete();
                $table->string('line_hash', 64);
                $table->unsignedInteger('row_number')->nullable();
                $table->date('operation_date');
                $table->string('direction', 8);
                $table->decimal('amount', 14, 2);
                $table->string('currency', 3)->default('RUB');
                $table->decimal('exchange_rate', 12, 6)->nullable();
                $table->text('description');
                $table->string('status', 16)->default('pending');
                $table->string('source', 16)->default('import');
                $table->string('match_type', 24)->nullable();
                $table->unsignedTinyInteger('match_confidence')->default(0);
                $table->string('match_notes')->nullable();
                $table->foreignId('suggested_order_id')
                    ->nullable()
                    ->constrained('orders', indexName: 'mgmt_stmt_line_sugg_order_fk')
                    ->nullOnDelete();
                $table->foreignId('suggested_payment_schedule_id')
                    ->nullable()
                    ->constrained('payment_schedules', indexName: 'mgmt_stmt_line_sugg_pay_sched_fk')
                    ->nullOnDelete();
                $table->foreignId('suggested_category_id')
                    ->nullable()
                    ->constrained('management_expense_categories', indexName: 'mgmt_stmt_line_sugg_cat_fk')
                    ->nullOnDelete();
                $table->foreignId('suggested_user_id')
                    ->nullable()
                    ->constrained('users', indexName: 'mgmt_stmt_line_sugg_user_fk')
                    ->nullOnDelete();
                $table->foreignId('allocation_category_id')
                    ->nullable()
                    ->constrained('management_expense_categories', indexName: 'mgmt_stmt_line_alloc_cat_fk')
                    ->nullOnDelete();
                $table->foreignId('allocation_order_id')
                    ->nullable()
                    ->constrained('orders', indexName: 'mgmt_stmt_line_alloc_order_fk')
                    ->nullOnDelete();
                $table->foreignId('allocation_payment_schedule_id')
                    ->nullable()
                    ->constrained('payment_schedules', indexName: 'mgmt_stmt_line_alloc_pay_sched_fk')
                    ->nullOnDelete();
                $table->foreignId('allocation_user_id')
                    ->nullable()
                    ->constrained('users', indexName: 'mgmt_stmt_line_alloc_user_fk')
                    ->nullOnDelete();
                $table->decimal('allocation_amount', 14, 2)->nullable();
                $table->foreignId('allocated_by')
                    ->nullable()
                    ->constrained('users', indexName: 'mgmt_stmt_line_allocated_by_fk')
                    ->nullOnDelete();
                $table->timestamp('allocated_at')->nullable();
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users', indexName: 'mgmt_stmt_line_created_by_fk')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique(['bank_account_id', 'line_hash']);
            });
        }

        if (! Schema::hasTable('management_payroll_halves')) {
            Schema::create('management_payroll_halves', function (Blueprint $table): void {
                $table->id();
                $table->unsignedSmallInteger('year');
                $table->unsignedTinyInteger('month');
                $table->unsignedTinyInteger('half');
                $table->date('period_start');
                $table->date('period_end');
                $table->date('payment_date');
                $table->string('status', 16)->default('open');
                $table->timestamps();

                $table->unique(['year', 'month', 'half']);
            });
        }

        if (! Schema::hasTable('management_payroll_half_users')) {
            Schema::create('management_payroll_half_users', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('payroll_half_id')->constrained('management_payroll_halves')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('accrued_amount', 14, 2)->default(0);
                $table->decimal('paid_amount', 14, 2)->default(0);
                $table->timestamps();

                $table->unique(['payroll_half_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('management_payroll_half_users');
        Schema::dropIfExists('management_payroll_halves');
        Schema::dropIfExists('management_statement_lines');
        Schema::dropIfExists('management_statement_imports');
        Schema::dropIfExists('management_expense_categories');
        Schema::dropIfExists('management_bank_accounts');

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'can_management_accounting')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('can_management_accounting');
            });
        }
    }
};
