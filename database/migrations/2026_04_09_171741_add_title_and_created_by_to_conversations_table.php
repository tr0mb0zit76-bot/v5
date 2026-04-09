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
        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'title')) {
                $table->string('title')->nullable()->after('type');
            }

            if (! Schema::hasColumn('conversations', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('title')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('conversations')) {
            return;
        }

        Schema::table('conversations', function (Blueprint $table) {
            if (Schema::hasColumn('conversations', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
            if (Schema::hasColumn('conversations', 'title')) {
                $table->dropColumn('title');
            }
        });
    }
};
