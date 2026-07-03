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
        if (! Schema::hasTable('load_board_posts')) {
            return;
        }

        Schema::table('load_board_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('load_board_posts', 'accepted_offer_id')) {
                $table->foreignId('accepted_offer_id')->nullable()->after('buyer_id')->constrained('load_board_offers')->nullOnDelete();
            }

            if (! Schema::hasColumn('load_board_posts', 'accepted_by')) {
                $table->foreignId('accepted_by')->nullable()->after('accepted_offer_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('load_board_posts', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('accepted_by');
            }

            if (! Schema::hasColumn('load_board_posts', 'metadata')) {
                $table->json('metadata')->nullable()->after('seller_comment');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('load_board_posts')) {
            return;
        }

        Schema::table('load_board_posts', function (Blueprint $table) {
            if (Schema::hasColumn('load_board_posts', 'accepted_offer_id')) {
                $table->dropConstrainedForeignId('accepted_offer_id');
            }

            if (Schema::hasColumn('load_board_posts', 'accepted_by')) {
                $table->dropConstrainedForeignId('accepted_by');
            }

            $columns = array_values(array_filter([
                Schema::hasColumn('load_board_posts', 'accepted_at') ? 'accepted_at' : null,
                Schema::hasColumn('load_board_posts', 'metadata') ? 'metadata' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
