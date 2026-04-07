<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Проверяет, существует ли колонка в таблице
     */
    private function hasColumn(string $table, string $column): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return Schema::hasColumn($table, $column);
    }

    /**
     * Проверяет, существует ли внешний ключ
     */
    private function foreignKeyExists(string $table, string $column): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $conn = Schema::getConnection();
        $database = $conn->getDatabaseName();

        if ($conn->getDriverName() === 'mysql') {
            $result = DB::select('
                SELECT COUNT(*) as count 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ', [$database, $table, $column]);

            return $result[0]->count > 0;
        }

        return false;
    }

    /**
     * Получает имя внешнего ключа
     */
    private function getForeignKeyName(string $table, string $column): ?string
    {
        $conn = Schema::getConnection();
        $database = $conn->getDatabaseName();

        if ($conn->getDriverName() === 'mysql') {
            $result = DB::select('
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = ? 
                AND TABLE_NAME = ? 
                AND COLUMN_NAME = ? 
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ', [$database, $table, $column]);

            return ! empty($result) ? $result[0]->CONSTRAINT_NAME : null;
        }

        return null;
    }

    /**
     * Безопасно удаляет внешний ключ
     */
    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        if (! $this->foreignKeyExists($table, $column)) {
            return;
        }

        $fkName = $this->getForeignKeyName($table, $column);
        if ($fkName && Schema::hasTable($table)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($fkName) {
                    $table->dropForeign($fkName);
                });
            } catch (Throwable $e) {
                logger()->warning("Failed to drop foreign key {$fkName} from {$table}: ".$e->getMessage());
            }
        }
    }

    /**
     * Безопасно добавляет внешний ключ
     */
    private function addForeignKeySafely(
        string $table,
        string $column,
        string $references,
        string $onTable,
        string $onDelete = 'CASCADE'
    ): void {
        if (! Schema::hasTable($table) || ! Schema::hasTable($onTable)) {
            return;
        }

        if (! $this->hasColumn($table, $column)) {
            return;
        }

        if (! $this->foreignKeyExists($table, $column)) {
            try {
                Schema::table($table, function (Blueprint $table) use ($column, $references, $onTable, $onDelete) {
                    $table->foreign($column)
                        ->references($references)
                        ->on($onTable)
                        ->onDelete($onDelete);
                });
            } catch (Throwable $e) {
                logger()->warning("Failed to add foreign key {$table}.{$column}: ".$e->getMessage());
            }
        }
    }

    public function up(): void
    {
        // 1. Создаём таблицу print_form_templates
        if (! Schema::hasTable('print_form_templates')) {
            Schema::create('print_form_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 100)->unique();
                $table->string('name');
                $table->string('document_type', 50)->index();
                $table->string('document_group', 50)->index();
                $table->string('party', 50)->default('internal')->index();
                $table->string('vue_component', 255);
                $table->string('pdf_view', 255)->nullable();
                $table->boolean('requires_internal_signature')->default(true);
                $table->boolean('requires_counterparty_signature')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('version')->default(1);
                $table->json('settings')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });

            // Добавляем внешние ключи для print_form_templates
            if (Schema::hasTable('users')) {
                $this->addForeignKeySafely('print_form_templates', 'created_by', 'id', 'users', 'SET NULL');
                $this->addForeignKeySafely('print_form_templates', 'updated_by', 'id', 'users', 'SET NULL');
            }
        }

        // 2. Добавляем колонки в order_documents
        if (Schema::hasTable('order_documents')) {
            Schema::table('order_documents', function (Blueprint $table) {
                if (! $this->hasColumn('order_documents', 'document_group')) {
                    $table->string('document_group', 50)->nullable()->after('type');
                }

                if (! $this->hasColumn('order_documents', 'source')) {
                    $table->string('source', 50)->default('uploaded')->after('document_group');
                }

                if (! $this->hasColumn('order_documents', 'signature_status')) {
                    $table->string('signature_status', 50)->default('not_requested')->after('status');
                }

                if (! $this->hasColumn('order_documents', 'requires_counterparty_signature')) {
                    $table->boolean('requires_counterparty_signature')->default(false)->after('signature_status');
                }

                if (! $this->hasColumn('order_documents', 'approval_requested_at')) {
                    $table->timestamp('approval_requested_at')->nullable()->after('requires_counterparty_signature');
                }

                if (! $this->hasColumn('order_documents', 'approval_requested_by')) {
                    $table->unsignedBigInteger('approval_requested_by')->nullable()->after('approval_requested_at');
                }

                if (! $this->hasColumn('order_documents', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approval_requested_by');
                }

                if (! $this->hasColumn('order_documents', 'approved_by')) {
                    $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
                }

                if (! $this->hasColumn('order_documents', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('approved_by');
                }

                if (! $this->hasColumn('order_documents', 'rejected_by')) {
                    $table->unsignedBigInteger('rejected_by')->nullable()->after('rejected_at');
                }

                if (! $this->hasColumn('order_documents', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('rejected_by');
                }

                if (! $this->hasColumn('order_documents', 'internal_signed_at')) {
                    $table->timestamp('internal_signed_at')->nullable()->after('rejection_reason');
                }

                if (! $this->hasColumn('order_documents', 'internal_signed_by')) {
                    $table->unsignedBigInteger('internal_signed_by')->nullable()->after('internal_signed_at');
                }

                if (! $this->hasColumn('order_documents', 'internal_signed_file_path')) {
                    $table->string('internal_signed_file_path')->nullable()->after('internal_signed_by');
                }

                if (! $this->hasColumn('order_documents', 'counterparty_signed_at')) {
                    $table->timestamp('counterparty_signed_at')->nullable()->after('internal_signed_file_path');
                }

                if (! $this->hasColumn('order_documents', 'counterparty_signed_file_path')) {
                    $table->string('counterparty_signed_file_path')->nullable()->after('counterparty_signed_at');
                }

                if (! $this->hasColumn('order_documents', 'snapshot_payload')) {
                    $table->json('snapshot_payload')->nullable()->after('counterparty_signed_file_path');
                }
            });

            // Добавляем внешние ключи для order_documents
            if (Schema::hasTable('print_form_templates') && $this->hasColumn('order_documents', 'template_id')) {
                $this->addForeignKeySafely('order_documents', 'template_id', 'id', 'print_form_templates', 'SET NULL');
            }

            if (Schema::hasTable('users')) {
                $userColumns = ['approval_requested_by', 'approved_by', 'rejected_by', 'internal_signed_by'];
                foreach ($userColumns as $column) {
                    if ($this->hasColumn('order_documents', $column)) {
                        $this->addForeignKeySafely('order_documents', $column, 'id', 'users', 'SET NULL');
                    }
                }
            }
        }

        // 3. Создаём таблицу notifications (если её нет)
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // 1. Удаляем внешние ключи из order_documents
        if (Schema::hasTable('order_documents')) {
            $foreignKeys = [
                'template_id',
                'approval_requested_by',
                'approved_by',
                'rejected_by',
                'internal_signed_by',
            ];

            foreach ($foreignKeys as $column) {
                $this->dropForeignKeyIfExists('order_documents', $column);
            }

            // Удаляем добавленные колонки
            $columnsToDrop = [];
            $columns = [
                'document_group',
                'source',
                'signature_status',
                'requires_counterparty_signature',
                'approval_requested_at',
                'approval_requested_by',
                'approved_at',
                'approved_by',
                'rejected_at',
                'rejected_by',
                'rejection_reason',
                'internal_signed_at',
                'internal_signed_by',
                'internal_signed_file_path',
                'counterparty_signed_at',
                'counterparty_signed_file_path',
                'snapshot_payload',
            ];

            foreach ($columns as $column) {
                if ($this->hasColumn('order_documents', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (! empty($columnsToDrop)) {
                Schema::table('order_documents', function (Blueprint $table) use ($columnsToDrop) {
                    $table->dropColumn($columnsToDrop);
                });
            }
        }

        // 2. Удаляем внешние ключи и таблицу print_form_templates
        if (Schema::hasTable('print_form_templates')) {
            $this->dropForeignKeyIfExists('print_form_templates', 'created_by');
            $this->dropForeignKeyIfExists('print_form_templates', 'updated_by');

            Schema::dropIfExists('print_form_templates');
        }

        // 3. Удаляем таблицу notifications
        Schema::dropIfExists('notifications');
    }
};
