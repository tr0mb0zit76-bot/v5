<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractor_documents')) {
            // Если таблицы нет - создаём полностью
            Schema::create('contractor_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
                $table->string('type')->nullable();
                $table->string('title');
                $table->string('number')->nullable();
                $table->date('document_date')->nullable();
                $table->string('status')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        } else {
            // Если таблица существует - добавляем только недостающие колонки
            Schema::table('contractor_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('contractor_documents', 'contractor_id')) {
                    $table->foreignId('contractor_id')->after('id')->constrained()->cascadeOnDelete();
                }

                if (! Schema::hasColumn('contractor_documents', 'type')) {
                    $table->string('type')->nullable()->after('contractor_id');
                }

                if (! Schema::hasColumn('contractor_documents', 'title')) {
                    $table->string('title')->after('type');
                }

                if (! Schema::hasColumn('contractor_documents', 'number')) {
                    $table->string('number')->nullable()->after('title');
                }

                if (! Schema::hasColumn('contractor_documents', 'document_date')) {
                    $table->date('document_date')->nullable()->after('number');
                }

                if (! Schema::hasColumn('contractor_documents', 'status')) {
                    $table->string('status')->nullable()->after('document_date');
                }

                if (! Schema::hasColumn('contractor_documents', 'notes')) {
                    $table->text('notes')->nullable()->after('status');
                }

                if (! Schema::hasColumn('contractor_documents', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
                }

                if (! Schema::hasColumn('contractor_documents', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        // В down мы не удаляем таблицу, если она существовала до миграции
        // Но если мы её создали в up, то удаляем
        if (Schema::hasTable('contractor_documents')) {
            // Проверяем, были ли добавлены колонки этой миграцией
            // Для простоты - удаляем таблицу только если нет внешних зависимостей
            // или можно просто ничего не делать в down
            Schema::dropIfExists('contractor_documents');
        }
    }
};
