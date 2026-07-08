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
        if (Schema::hasTable('load_board_offers') && ! Schema::hasColumn('load_board_offers', 'source')) {
            Schema::table('load_board_offers', function (Blueprint $table) {
                $table->string('source', 32)->default('internal_crm')->after('status');
            });
        }

        if (Schema::hasTable('load_board_rate_observations')) {
            return;
        }

        Schema::create('load_board_rate_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('load_board_post_id')->constrained('load_board_posts')->cascadeOnDelete();
            $table->foreignId('load_board_offer_id')->nullable()->constrained('load_board_offers')->nullOnDelete();
            $table->foreignId('carrier_id')->nullable()->constrained('contractors')->nullOnDelete();
            $table->string('corridor_key', 64)->nullable()->index();
            $table->string('loading_location', 255)->nullable();
            $table->string('unloading_location', 255)->nullable();
            $table->string('truck_body_type_code', 64)->nullable();
            $table->decimal('cargo_weight', 10, 2)->nullable();
            $table->decimal('customer_rate', 14, 2)->nullable();
            $table->string('customer_rate_currency', 3)->default('RUB');
            $table->decimal('carrier_rate', 14, 2);
            $table->string('carrier_rate_currency', 3)->default('RUB');
            $table->decimal('margin_abs', 14, 2)->nullable();
            $table->decimal('margin_pct', 8, 2)->nullable();
            $table->string('source', 32)->default('internal_crm')->index();
            $table->string('outcome', 32)->default('open')->index();
            $table->timestamp('observed_at')->useCurrent();
            $table->timestamps();

            $table->index(['corridor_key', 'outcome', 'observed_at'], 'load_board_rate_obs_corridor_outcome_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('load_board_rate_observations');

        if (Schema::hasTable('load_board_offers') && Schema::hasColumn('load_board_offers', 'source')) {
            Schema::table('load_board_offers', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }
    }
};
