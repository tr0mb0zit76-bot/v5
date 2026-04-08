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
        // Таблица agent_conversations
        if (! Schema::hasTable('agent_conversations')) {
            Schema::create('agent_conversations', function (Blueprint $table) {
                $table->uuid('id')->primary();

                // Внешний ключ для user_id
                if (Schema::hasTable('users')) {
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->index('user_id');
                }

                $table->string('title');
                $table->timestamps();

                $table->index(['user_id', 'updated_at']);
            });
        }

        // Таблица agent_conversation_messages
        if (! Schema::hasTable('agent_conversation_messages')) {
            Schema::create('agent_conversation_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();

                // Внешний ключ для conversation_id
                if (Schema::hasTable('agent_conversations')) {
                    $table->foreignUuid('conversation_id')
                        ->constrained('agent_conversations')
                        ->cascadeOnDelete();
                } else {
                    $table->string('conversation_id', 36);
                    $table->index('conversation_id');
                }

                // Внешний ключ для user_id
                if (Schema::hasTable('users')) {
                    $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                } else {
                    $table->unsignedBigInteger('user_id')->nullable();
                    $table->index('user_id');
                }

                $table->string('agent');
                $table->string('role', 25);
                $table->longText('content')->nullable();
                $table->json('attachments')->nullable();     // JSON вместо TEXT
                $table->json('tool_calls')->nullable();      // JSON вместо TEXT
                $table->json('tool_results')->nullable();    // JSON вместо TEXT
                $table->json('usage')->nullable();           // JSON вместо TEXT
                $table->json('meta')->nullable();            // JSON вместо TEXT
                $table->timestamps();

                // Оптимизированные индексы
                $table->index(['conversation_id', 'user_id', 'updated_at'], 'conversation_index');
                $table->index(['conversation_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
                $table->index(['role']);
                $table->index(['agent']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Удаляем внешние ключи перед удалением таблиц
        if (Schema::hasTable('agent_conversation_messages')) {
            try {
                Schema::table('agent_conversation_messages', function (Blueprint $table) {
                    $table->dropForeign(['conversation_id']);
                    $table->dropForeign(['user_id']);
                });
            } catch (Throwable $e) {
                // Логируем ошибку, но продолжаем
                logger()->warning('Failed to drop foreign keys from agent_conversation_messages: '.$e->getMessage());
            }

            Schema::dropIfExists('agent_conversation_messages');
        }

        if (Schema::hasTable('agent_conversations')) {
            try {
                Schema::table('agent_conversations', function (Blueprint $table) {
                    $table->dropForeign(['user_id']);
                });
            } catch (Throwable $e) {
                logger()->warning('Failed to drop foreign keys from agent_conversations: '.$e->getMessage());
            }

            Schema::dropIfExists('agent_conversations');
        }
    }
};
