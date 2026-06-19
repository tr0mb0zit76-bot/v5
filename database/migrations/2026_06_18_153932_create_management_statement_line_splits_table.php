<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_LINE_TYPE = 'mgmt_stmt_line_split_line_type_idx';

    public function up(): void
    {
        if (! Schema::hasTable('management_statement_line_splits')) {
            Schema::create('management_statement_line_splits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('management_statement_line_id')
                    ->constrained('management_statement_lines', indexName: 'mgmt_stmt_line_split_line_fk')
                    ->cascadeOnDelete();
                $table->string('allocation_type', 24);
                $table->foreignId('payment_schedule_id')
                    ->nullable()
                    ->constrained('payment_schedules', indexName: 'mgmt_stmt_line_split_sched_fk')
                    ->nullOnDelete();
                $table->foreignId('order_id')
                    ->nullable()
                    ->constrained('orders', indexName: 'mgmt_stmt_line_split_order_fk')
                    ->nullOnDelete();
                $table->foreignId('category_id')
                    ->nullable()
                    ->constrained('management_expense_categories', indexName: 'mgmt_stmt_line_split_cat_fk')
                    ->nullOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users', indexName: 'mgmt_stmt_line_split_user_fk')
                    ->nullOnDelete();
                $table->decimal('amount', 14, 2);
                $table->timestamps();

                $table->index(
                    ['management_statement_line_id', 'allocation_type'],
                    self::INDEX_LINE_TYPE,
                );
            });

            return;
        }

        if ($this->indexExists('management_statement_line_splits', self::INDEX_LINE_TYPE)) {
            return;
        }

        Schema::table('management_statement_line_splits', function (Blueprint $table): void {
            $table->index(
                ['management_statement_line_id', 'allocation_type'],
                self::INDEX_LINE_TYPE,
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('management_statement_line_splits');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return $rows !== [];
    }
};
