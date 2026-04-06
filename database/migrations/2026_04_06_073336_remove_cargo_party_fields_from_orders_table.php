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
            $columnsToDrop = [];

            if (Schema::hasColumn('orders', 'cargo_sender_name')) {
                $columnsToDrop[] = 'cargo_sender_name';
            }
            if (Schema::hasColumn('orders', 'cargo_sender_address')) {
                $columnsToDrop[] = 'cargo_sender_address';
            }
            if (Schema::hasColumn('orders', 'cargo_sender_contact')) {
                $columnsToDrop[] = 'cargo_sender_contact';
            }
            if (Schema::hasColumn('orders', 'cargo_sender_phone')) {
                $columnsToDrop[] = 'cargo_sender_phone';
            }
            if (Schema::hasColumn('orders', 'cargo_recipient_name')) {
                $columnsToDrop[] = 'cargo_recipient_name';
            }
            if (Schema::hasColumn('orders', 'cargo_recipient_address')) {
                $columnsToDrop[] = 'cargo_recipient_address';
            }
            if (Schema::hasColumn('orders', 'cargo_recipient_contact')) {
                $columnsToDrop[] = 'cargo_recipient_contact';
            }
            if (Schema::hasColumn('orders', 'cargo_recipient_phone')) {
                $columnsToDrop[] = 'cargo_recipient_phone';
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
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
            if (! Schema::hasColumn('orders', 'cargo_sender_name')) {
                $table->string('cargo_sender_name')->nullable();
            }
            if (! Schema::hasColumn('orders', 'cargo_sender_address')) {
                $table->text('cargo_sender_address')->nullable();
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
                $table->text('cargo_recipient_address')->nullable();
            }
            if (! Schema::hasColumn('orders', 'cargo_recipient_contact')) {
                $table->string('cargo_recipient_contact')->nullable();
            }
            if (! Schema::hasColumn('orders', 'cargo_recipient_phone')) {
                $table->string('cargo_recipient_phone', 50)->nullable();
            }
        });
    }
};
