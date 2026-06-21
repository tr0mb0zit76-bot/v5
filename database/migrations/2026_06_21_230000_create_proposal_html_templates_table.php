<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('proposal_html_templates')) {
            return;
        }

        Schema::create('proposal_html_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->longText('html_body');
            $table->longText('css_inline')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility')->default('workspace');
            $table->timestamps();
        });

        if (Schema::hasTable('proposal_html_template_variables')) {
            return;
        }

        Schema::create('proposal_html_template_variables', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->string('label');
            $table->string('group_name')->default('lead');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_html_template_variables');
        Schema::dropIfExists('proposal_html_templates');
    }
};
