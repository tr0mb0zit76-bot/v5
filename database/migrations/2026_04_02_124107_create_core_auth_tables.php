<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sites')) {
            Schema::create('sites', function (Blueprint $table) {
                $table->tinyIncrements('id');
                $table->string('domain', 100)->unique();
                $table->string('name', 100);
                $table->string('theme', 50)->default('default');
                $table->string('home_url', 255)->default('/');
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('display_name')->nullable();
                $table->text('description')->nullable();
                $table->json('permissions')->nullable();
                $table->json('visibility_areas')->nullable();
                $table->json('visibility_scopes')->nullable();
                $table->json('columns_config')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->unsignedTinyInteger('site_id')->nullable();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('theme', 20)->default('light');
                $table->boolean('is_active')->default(true);
                $table->json('ai_preferences')->nullable();
                $table->boolean('ai_learning_enabled')->default(true);
                $table->rememberToken();
                $table->timestamps();

                $table->foreign('site_id')->references('id')->on('sites')->nullOnDelete();
                $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();

                $table->index('site_id');
                $table->index('role_id');
                $table->index(['site_id', 'role_id']);
            });
        }

        if (! Schema::hasTable('password_reset_tokens')) {
            Schema::create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('sites');
    }
};
