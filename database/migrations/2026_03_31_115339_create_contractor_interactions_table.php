<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Проверяем, существует ли таблица
        if (! Schema::hasTable('contractor_interactions')) {
            // Таблицы нет — создаём с нуля
            Schema::create('contractor_interactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
                $table->timestamp('contacted_at')->nullable();
                $table->string('channel', 50)->nullable();
                $table->string('subject')->nullable();
                $table->text('summary')->nullable();
                $table->string('result')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        } else {
            // Таблица существует — проверяем каждый столбец и добавляем, если отсутствует
            Schema::table('contractor_interactions', function (Blueprint $table) {
                // Проверяем и добавляем id
                if (! Schema::hasColumn('contractor_interactions', 'id')) {
                    $table->id();
                }

                // Проверяем и добавляем contractor_id
                if (! Schema::hasColumn('contractor_interactions', 'contractor_id')) {
                    $table->foreignId('contractor_id')->after('id')->constrained()->cascadeOnDelete();
                }

                // Проверяем и добавляем contacted_at
                if (! Schema::hasColumn('contractor_interactions', 'contacted_at')) {
                    $table->timestamp('contacted_at')->nullable()->after('contractor_id');
                }

                // Проверяем и добавляем channel
                if (! Schema::hasColumn('contractor_interactions', 'channel')) {
                    $table->string('channel', 50)->nullable()->after('contacted_at');
                }

                // Проверяем и добавляем subject
                if (! Schema::hasColumn('contractor_interactions', 'subject')) {
                    $table->string('subject')->nullable()->after('channel');
                }

                // Проверяем и добавляем summary
                if (! Schema::hasColumn('contractor_interactions', 'summary')) {
                    $table->text('summary')->nullable()->after('subject');
                }

                // Проверяем и добавляем result
                if (! Schema::hasColumn('contractor_interactions', 'result')) {
                    $table->string('result')->nullable()->after('summary');
                }

                // Проверяем и добавляем created_by
                if (! Schema::hasColumn('contractor_interactions', 'created_by')) {
                    $table->foreignId('created_by')->nullable()->after('result')->constrained('users')->nullOnDelete();
                }

                // Проверяем наличие полей timestamps (created_at, updated_at)
                if (! Schema::hasColumn('contractor_interactions', 'created_at')) {
                    $table->timestamps();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_interactions');
    }
};
