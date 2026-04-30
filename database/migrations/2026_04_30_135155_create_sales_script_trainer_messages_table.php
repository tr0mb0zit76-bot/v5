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
        if (Schema::hasTable('sales_script_trainer_messages')) {
            return;
        }

        Schema::create('sales_script_trainer_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sales_script_play_session_id');
            $table->foreign('sales_script_play_session_id', 'fk_sstm_play_session')
                ->references('id')
                ->on('sales_script_play_sessions')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id', 'fk_sstm_user')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
            $table->string('role', 20);
            $table->text('content');
            $table->timestamps();
            $table->index(['sales_script_play_session_id', 'id'], 'sstm_sess_id_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_script_trainer_messages');
    }
};
