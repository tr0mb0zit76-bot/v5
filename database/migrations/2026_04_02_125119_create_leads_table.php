<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads')) {
            return;
        }

        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('status', 50)->default('new')->index();
            $table->string('source', 100)->nullable();
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->unsignedBigInteger('responsible_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('transport_type', 100)->nullable();
            $table->string('loading_location', 255)->nullable();
            $table->string('unloading_location', 255)->nullable();
            $table->date('planned_shipping_date')->nullable();
            $table->decimal('target_price', 12, 2)->nullable();
            $table->string('target_currency', 3)->default('RUB');
            $table->decimal('calculated_cost', 12, 2)->nullable();
            $table->decimal('expected_margin', 12, 2)->nullable();
            $table->timestamp('proposal_sent_at')->nullable();
            $table->timestamp('next_contact_at')->nullable();
            $table->string('lost_reason')->nullable();
            $table->json('lead_qualification')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
