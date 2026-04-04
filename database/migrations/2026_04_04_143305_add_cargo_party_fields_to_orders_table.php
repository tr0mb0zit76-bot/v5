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

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'cargo_sender_name')) {
                $table->string('cargo_sender_name')->nullable();
            }

            if (! Schema::hasColumn('orders', 'cargo_sender_address')) {
                $table->string('cargo_sender_address', 500)->nullable();
            }

            if (! Schema::hasColumn('orders', 'cargo_sender_contact')) {
                $table->string('cargo_sender_contact')->nullable();
            }

            if (! Schema::hasColumn('orders', 'cargo_sender_phone')) {
                $table->string('cargo_sender_phone', 50)->nullable();
            }

            if (! Schema::hasColumn('orders', 'cargo_recipient_name')) {
                $table->string('cargo_recipient_name')->nullable();
            }

            if (! Schema::hasColumn('orders', 'cargo_recipient_address')) {
                $table->string('cargo_recipient_address', 500)->nullable();
            }

            if (! Schema::hasColumn('orders', 'cargo_recipient_contact')) {
                $table->string('cargo_recipient_contact')->nullable();
            }

            if (! Schema::hasColumn('orders', 'cargo_recipient_phone')) {
                $table->string('cargo_recipient_phone', 50)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('orders', 'cargo_sender_name') ? 'cargo_sender_name' : null,
                Schema::hasColumn('orders', 'cargo_sender_address') ? 'cargo_sender_address' : null,
                Schema::hasColumn('orders', 'cargo_sender_contact') ? 'cargo_sender_contact' : null,
                Schema::hasColumn('orders', 'cargo_sender_phone') ? 'cargo_sender_phone' : null,
                Schema::hasColumn('orders', 'cargo_recipient_name') ? 'cargo_recipient_name' : null,
                Schema::hasColumn('orders', 'cargo_recipient_address') ? 'cargo_recipient_address' : null,
                Schema::hasColumn('orders', 'cargo_recipient_contact') ? 'cargo_recipient_contact' : null,
                Schema::hasColumn('orders', 'cargo_recipient_phone') ? 'cargo_recipient_phone' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
