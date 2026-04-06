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
        Schema::table('route_points', function (Blueprint $table) {
            if (! Schema::hasColumn('route_points', 'sender_name')) {
                $table->string('sender_name')->nullable()->after('contact_phone');
            }

            if (! Schema::hasColumn('route_points', 'sender_contact')) {
                $table->string('sender_contact')->nullable()->after('sender_name');
            }

            if (! Schema::hasColumn('route_points', 'sender_phone')) {
                $table->string('sender_phone', 50)->nullable()->after('sender_contact');
            }

            if (! Schema::hasColumn('route_points', 'recipient_name')) {
                $table->string('recipient_name')->nullable()->after('sender_phone');
            }

            if (! Schema::hasColumn('route_points', 'recipient_contact')) {
                $table->string('recipient_contact')->nullable()->after('recipient_name');
            }

            if (! Schema::hasColumn('route_points', 'recipient_phone')) {
                $table->string('recipient_phone', 50)->nullable()->after('recipient_contact');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('route_points', function (Blueprint $table) {
            $columns = [
                'sender_name',
                'sender_contact',
                'sender_phone',
                'recipient_name',
                'recipient_contact',
                'recipient_phone',
            ];

            $columnsToDrop = array_filter($columns, fn ($column) => Schema::hasColumn('route_points', $column));

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
