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
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cargo_sender_name')->nullable()->after('special_notes');
            $table->string('cargo_sender_address', 500)->nullable()->after('cargo_sender_name');
            $table->string('cargo_sender_contact')->nullable()->after('cargo_sender_address');
            $table->string('cargo_sender_phone', 50)->nullable()->after('cargo_sender_contact');
            $table->string('cargo_recipient_name')->nullable()->after('cargo_sender_phone');
            $table->string('cargo_recipient_address', 500)->nullable()->after('cargo_recipient_name');
            $table->string('cargo_recipient_contact')->nullable()->after('cargo_recipient_address');
            $table->string('cargo_recipient_phone', 50)->nullable()->after('cargo_recipient_contact');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'cargo_sender_name',
                'cargo_sender_address',
                'cargo_sender_contact',
                'cargo_sender_phone',
                'cargo_recipient_name',
                'cargo_recipient_address',
                'cargo_recipient_contact',
                'cargo_recipient_phone',
            ]);
        });
    }
};
