<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contractor_enrichment_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('status', 32);
            $table->string('trigger', 32);
            $table->json('sources_json')->nullable();
            $table->json('dossier_json')->nullable();
            $table->json('proposed_drafts_json')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['contractor_id', 'status']);
            $table->index(['contractor_id', 'finished_at']);
        });

        if (Schema::hasTable('contractor_insight_drafts')
            && ! Schema::hasColumn('contractor_insight_drafts', 'source_url')) {
            Schema::table('contractor_insight_drafts', function (Blueprint $table) {
                $table->string('source_url', 500)->nullable()->after('source_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contractor_insight_drafts')
            && Schema::hasColumn('contractor_insight_drafts', 'source_url')) {
            Schema::table('contractor_insight_drafts', function (Blueprint $table) {
                $table->dropColumn('source_url');
            });
        }

        Schema::dropIfExists('contractor_enrichment_runs');
    }
};
