<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Проверяем, существует ли таблица
        if (! Schema::hasTable('contractor_documents')) {
            // Таблицы нет — создаём с нуля
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
            // Таблица существует — проверяем каждый столбец и добавляем, если отсутствует
            Schema::table('contractor_documents', function (Blueprint $table) {
                // Проверяем и добавляем id (обычно он есть, но на всякий случай)
                if (! Schema::hasColumn('contractor_documents', 'id')) {
                    $table->id();
                }

                // Проверяем и добавляем contractor_id
                if (! Schema::hasColumn('contractor_documents', 'contractor_id')) {
                    $table->foreignId('contractor_id')->after('id')->constrained()->cascadeOnDelete();
                }

                // Проверяем и добавляем type
                if (! Schema::hasColumn('contractor_documents', 'type')) {
                    $table->string('type')->nullable()->after('contractor_id');
                }

                // Проверяем и добавляем title
                if (! Schema::hasColumn('contractor_documents', 'title')) {
                    $table->string('title')->after('type');
                }

                // Проверяем и добавляем number
                if (! Schema::hasColumn('contractor_documents', 'number')) {
                    $table->string('number')->nullable()->after('title');
                }

                // Проверяем и добавляем document_date
                if (! Schema::hasColumn('contractor_documents', 'document_date')) {
                    $table->date('document_date')->nullable()->after('number');
                }

                // Проверяем и добавляем status
                if (! Schema::hasColumn('contractor_documents', 'status')) {
                    $table->string('status')->nullable()->after('document_date');
                }

                // Проверяем и добавляем notes
                if (! Schema::hasColumn('contractor_documents', 'notes')) {
                    $table->text('notes')->nullable()->after('status');
                }

                // Проверяем и добавляем created_by
                if (! Schema::hasColumn('contractor_documents', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
                }

                // Проверяем наличие полей timestamps (created_at, updated_at)
                if (! Schema::hasColumn('contractor_documents', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_documents');
    }
};
