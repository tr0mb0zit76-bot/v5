<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractors', 'debt_limit')) {
                $table->decimal('debt_limit', 12, 2)->nullable()->after('metadata');
            }

            if (! Schema::hasColumn('contractors', 'debt_limit_currency')) {
                $table->string('debt_limit_currency', 3)->default('RUB')->after('debt_limit');
            }

            if (! Schema::hasColumn('contractors', 'stop_on_limit')) {
                $table->boolean('stop_on_limit')->default(false)->after('debt_limit_currency');
            }

            if (! Schema::hasColumn('contractors', 'default_customer_payment_form')) {
                $table->string('default_customer_payment_form', 50)->nullable()->after('stop_on_limit');
            }

            if (! Schema::hasColumn('contractors', 'default_customer_payment_term')) {
                $table->string('default_customer_payment_term')->nullable()->after('default_customer_payment_form');
            }

            if (! Schema::hasColumn('contractors', 'default_carrier_payment_form')) {
                $table->string('default_carrier_payment_form', 50)->nullable()->after('default_customer_payment_term');
            }

            if (! Schema::hasColumn('contractors', 'default_carrier_payment_term')) {
                $table->string('default_carrier_payment_term')->nullable()->after('default_carrier_payment_form');
            }

            if (! Schema::hasColumn('contractors', 'cooperation_terms_notes')) {
                $table->text('cooperation_terms_notes')->nullable()->after('default_carrier_payment_term');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            $columns = [
                'debt_limit',
                'debt_limit_currency',
                'stop_on_limit',
                'default_customer_payment_form',
                'default_customer_payment_term',
                'default_carrier_payment_form',
                'default_carrier_payment_term',
                'cooperation_terms_notes',
            ];

            $existingColumns = array_values(array_filter($columns, static fn (string $column): bool => Schema::hasColumn('contractors', $column)));

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }
};
