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
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20)->default('direct');
                $table->timestamps();
            });
        }

        Schema::table('conversations', function (Blueprint $table) {
            if (! Schema::hasColumn('conversations', 'title')) {
                $table->string('title')->nullable()->after('type');
            }
            if (! Schema::hasColumn('conversations', 'created_by') && Schema::hasTable('users')) {
                $table->foreignId('created_by')->nullable()->after('title')->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('conversation_participants')) {
            Schema::create('conversation_participants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('last_read_at')->nullable();
                $table->timestamps();

                $table->unique(['conversation_id', 'user_id']);
            });
        } else {
            Schema::table('conversation_participants', function (Blueprint $table) {
                if (! Schema::hasColumn('conversation_participants', 'last_read_at')) {
                    $table->timestamp('last_read_at')->nullable()->after('user_id');
                }
            });
        }

        if (! Schema::hasTable('chat_messages')) {
            Schema::create('chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                if (Schema::hasTable('users')) {
                    $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('recipient_user_id')->nullable();
                }
                $table->text('body');
                $table->timestamps();
            });
        } else {
            Schema::table('chat_messages', function (Blueprint $table) {
                if (! Schema::hasColumn('chat_messages', 'recipient_user_id') && Schema::hasTable('users')) {
                    $table->foreignId('recipient_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Intentionally left empty: forward-fix migration for production schema drift.
    }
};
