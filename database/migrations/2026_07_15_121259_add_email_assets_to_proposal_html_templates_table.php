<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('proposal_html_templates')) {
            return;
        }

        if (Schema::hasColumn('proposal_html_templates', 'email_assets')) {
            return;
        }

        Schema::table('proposal_html_templates', function (Blueprint $table) {
            $table->json('email_assets')->nullable()->after('css_inline');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('proposal_html_templates')) {
            return;
        }

        if (! Schema::hasColumn('proposal_html_templates', 'email_assets')) {
            return;
        }

        Schema::table('proposal_html_templates', function (Blueprint $table) {
            $table->dropColumn('email_assets');
        });
    }
};
