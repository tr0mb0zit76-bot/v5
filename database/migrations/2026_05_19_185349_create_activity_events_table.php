<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_events')) {
            return;
        }

        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('event_type', 80);
            $table->string('title')->nullable();
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('occurred_at');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->nullableMorphs('source');
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'occurred_at'], 'activity_events_subject_occurred_idx');
            $table->index(['event_type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
    }
};
