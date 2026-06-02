<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            if (! Schema::hasColumn('users', 'ai_preferences')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->json('ai_preferences')->nullable();
                });
            }

            if (! Schema::hasColumn('users', 'ai_learning_enabled')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->boolean('ai_learning_enabled')->default(true);
                });
            }

            if (! Schema::hasColumn('users', 'mobile_nav_keys')) {
                if (Schema::hasColumn('users', 'ai_preferences')) {
                    Schema::table('users', function (Blueprint $table): void {
                        $table->json('mobile_nav_keys')->nullable()->after('ai_preferences');
                    });
                } else {
                    Schema::table('users', function (Blueprint $table): void {
                        $table->json('mobile_nav_keys')->nullable();
                    });
                }
            }
        }

        if (Schema::hasTable('roles') && ! Schema::hasColumn('roles', 'default_mobile_nav_keys')) {
            if (Schema::hasColumn('roles', 'columns_config')) {
                Schema::table('roles', function (Blueprint $table): void {
                    $table->json('default_mobile_nav_keys')->nullable()->after('columns_config');
                });
            } else {
                Schema::table('roles', function (Blueprint $table): void {
                    $table->json('default_mobile_nav_keys')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'mobile_nav_keys')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('mobile_nav_keys');
            });
        }

        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'default_mobile_nav_keys')) {
            Schema::table('roles', function (Blueprint $table): void {
                $table->dropColumn('default_mobile_nav_keys');
            });
        }
    }
};
