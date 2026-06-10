<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('management_statement_lines') || $this->hasShortForeignKeys()) {
            return;
        }

        Schema::drop('management_statement_lines');

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

    public function down(): void
    {
        // No-op: schema is owned by create_management_accounting_tables migration.
    }

    private function hasShortForeignKeys(): bool
    {
        $constraint = DB::selectOne(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = ?
               AND CONSTRAINT_NAME = ?
               AND CONSTRAINT_TYPE = ?',
            ['management_statement_lines', 'mgmt_stmt_line_alloc_pay_sched_fk', 'FOREIGN KEY']
        );

        return $constraint !== null;
    }
};
