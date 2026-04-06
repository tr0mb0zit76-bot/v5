<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fields are now created in the create_route_points_table migration
        // This migration is kept for backwards compatibility
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Fields are now managed in the create_route_points_table migration
        // This migration is kept for backwards compatibility
    }
};
