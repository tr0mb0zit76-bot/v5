<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * После удаления колонки orders.payment_terms JSON с графиком оплаты клиента
     * и режимом заявки больше не сохранялся; карточка при F5 брала пустой decode.
     * Дублируем тот же JSON, что раньше писали в orders.payment_terms, в financial_terms.
     */
    public function up(): void
    {
        if (! Schema::hasTable('financial_terms')) {
            return;
        }

        if (Schema::hasColumn('financial_terms', 'payment_terms_snapshot')) {
            return;
        }

        Schema::table('financial_terms', function (Blueprint $table): void {
            $table->text('payment_terms_snapshot')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('financial_terms')) {
            return;
        }

        if (! Schema::hasColumn('financial_terms', 'payment_terms_snapshot')) {
            return;
        }

        Schema::table('financial_terms', function (Blueprint $table): void {
            $table->dropColumn('payment_terms_snapshot');
        });
    }
};
