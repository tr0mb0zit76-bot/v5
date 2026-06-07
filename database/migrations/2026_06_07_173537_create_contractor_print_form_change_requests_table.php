<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contractor_print_form_change_requests')) {
            return;
        }

        Schema::create('contractor_print_form_change_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->string('party', 16);
            $table->string('change_type', 32)->default('basic_terms');
            $table->string('status', 32)->default('pending_approval');
            $table->json('payload')->nullable();
            $table->text('manager_notes')->nullable();
            $table->text('yurik_summary')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'status']);
            $table->index(['party', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_print_form_change_requests');
    }
};
