<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('one_c_epd_registry_entries', function (Blueprint $table) {
            $table->id();
            $table->string('publication_code', 32);
            $table->uuid('document_ref');
            $table->string('document_ref_type', 255)->nullable();
            $table->string('type_ref_key', 64)->nullable();
            $table->string('document_type', 64);
            $table->string('document_type_label', 191)->nullable();
            $table->string('epd_number', 64)->nullable();
            $table->date('epd_date')->nullable();
            $table->string('ib_number', 64)->nullable();
            $table->dateTime('ib_date')->nullable();
            $table->string('current_step', 128)->nullable();
            $table->boolean('current_step_done')->default(false);
            $table->unsignedTinyInteger('participant_role')->nullable();
            $table->uuid('organization_ref')->nullable();
            $table->uuid('shipper_ref')->nullable();
            $table->string('shipper_name')->nullable();
            $table->string('shipper_inn', 32)->nullable();
            $table->uuid('consignee_ref')->nullable();
            $table->string('consignee_name')->nullable();
            $table->string('consignee_inn', 32)->nullable();
            $table->uuid('carrier_ref')->nullable();
            $table->string('carrier_name')->nullable();
            $table->string('carrier_inn', 32)->nullable();
            $table->boolean('posted')->default(false);
            $table->boolean('deletion_mark')->default(false);
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('linked_at')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['publication_code', 'document_ref'], 'one_c_epd_registry_pub_doc_unique');
            $table->index(['document_type', 'epd_date']);
            $table->index(['order_id']);
            $table->index(['shipper_inn']);
            $table->index(['current_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('one_c_epd_registry_entries');
    }
};
