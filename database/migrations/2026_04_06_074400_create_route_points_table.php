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
        if (Schema::hasTable('route_points')) {
            return;
        }

        Schema::create('route_points', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_leg_id')->constrained('order_legs')->cascadeOnDelete();
            $table->foreignId('address_id')->nullable()->constrained('addresses')->nullOnDelete();

            $table->enum('type', ['loading', 'unloading', 'transit', 'customs', 'warehouse'])->default('transit');
            $table->integer('sequence')->default(0);

            // сохраняем служебные данные точки
            $table->string('kladr_id')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->date('planned_date')->nullable();
            $table->time('planned_time_from')->nullable();
            $table->time('planned_time_to')->nullable();

            $table->date('actual_date')->nullable();
            $table->time('actual_time')->nullable();

            $table->string('contact_person')->nullable();
            $table->string('contact_phone', 50)->nullable();

            // sender/recipient fields
            $table->string('sender_name')->nullable();
            $table->string('sender_contact')->nullable();
            $table->string('sender_phone', 50)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_contact')->nullable();
            $table->string('recipient_phone', 50)->nullable();

            $table->text('instructions')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index(['order_leg_id', 'sequence'], 'route_points_order_leg_id_sequence_index');
            $table->index(['order_leg_id', 'type'], 'route_points_order_leg_id_type_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Table may already exist because it is created by the core schema migration.
        // Do not drop an existing route_points table during rollback of this compatibility migration.
    }
};
