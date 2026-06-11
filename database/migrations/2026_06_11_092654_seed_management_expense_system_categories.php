<?php

use App\Services\ManagementAccounting\ManagementExpenseCategorySyncService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Идемпотентно создаёт 10 системных статей (если migrate прошёл без db:seed).
     */
    public function up(): void
    {
        if (! Schema::hasTable('management_expense_categories')) {
            return;
        }

        app(ManagementExpenseCategorySyncService::class)->ensureSystemCategories();
    }

    public function down(): void
    {
        // Данные справочника не откатываем.
    }
};
