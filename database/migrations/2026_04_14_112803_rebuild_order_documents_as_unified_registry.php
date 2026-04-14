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
        Schema::dropIfExists('order_documents');

        Schema::create('order_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('entity_type', 40)->default('order');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('type', 80);
            $table->string('document_group', 50)->nullable();
            $table->string('source', 50)->default('uploaded');
            $table->string('number')->nullable();
            $table->date('document_date')->nullable();
            $table->string('original_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('generated_pdf_path')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('print_form_templates')->nullOnDelete();
            $table->string('status', 50)->default('draft');
            $table->string('workflow_status', 40)->nullable();
            $table->string('signature_status', 50)->default('not_requested');
            $table->boolean('requires_counterparty_signature')->default(false);
            $table->timestamp('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamp('approval_requested_at')->nullable();
            $table->foreignId('approval_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('internal_signed_at')->nullable();
            $table->foreignId('internal_signed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('internal_signed_file_path')->nullable();
            $table->timestamp('counterparty_signed_at')->nullable();
            $table->string('counterparty_signed_file_path')->nullable();
            $table->json('snapshot_payload')->nullable();
            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
            $table->index(['order_id', 'type']);
            $table->index(['status', 'workflow_status']);
            $table->index('document_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_documents');
    }
};
