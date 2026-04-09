<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $columnsToDrop = array_values(array_filter([
            'performers',
            'payment_terms',
            'carrier_rate',
            'carrier_payment_form',
            'carrier_payment_term',
        ], fn (string $column): bool => Schema::hasColumn('orders', $column)));

        if ($columnsToDrop !== []) {
            Schema::table('orders', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }

        if (Schema::hasColumn('orders', 'carrier_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('carrier_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'performers')) {
                $table->json('performers')->nullable()->after('metadata');
            }

            if (! Schema::hasColumn('orders', 'payment_terms')) {
                $table->text('payment_terms')->nullable()->after('special_notes');
            }

            if (! Schema::hasColumn('orders', 'carrier_rate')) {
                $table->decimal('carrier_rate', 10, 2)->nullable()->after('customer_rate');
            }

            if (! Schema::hasColumn('orders', 'carrier_payment_form')) {
                $table->string('carrier_payment_form')->nullable()->after('customer_payment_term');
            }

            if (! Schema::hasColumn('orders', 'carrier_payment_term')) {
                $table->string('carrier_payment_term')->nullable()->after('carrier_payment_form');
            }
        });

        if (Schema::hasColumn('orders', 'carrier_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unsignedBigInteger('carrier_id')->nullable(false)->change();
            });
        }
    }
};
