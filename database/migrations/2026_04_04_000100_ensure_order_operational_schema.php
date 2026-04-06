<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_terms') === false && Schema::hasTable('orders')) {
            Schema::create('financial_terms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->decimal('client_price', 12, 2)->nullable();
                $table->string('client_currency', 3)->default('RUB');
                $table->string('client_payment_terms')->nullable();
                $table->json('contractors_costs')->nullable();
                $table->decimal('total_cost', 12, 2)->default(0);
                $table->decimal('margin', 12, 2)->default(0);
                $table->json('additional_costs')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('order_documents')) {
            return;
        }

        Schema::table('order_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('order_documents', 'document_group')) {
                $table->string('document_group', 50)->nullable()->after('type');
            }

            if (! Schema::hasColumn('order_documents', 'source')) {
                $table->string('source', 50)->nullable()->after('document_group');
            }

            if (! Schema::hasColumn('order_documents', 'signature_status')) {
                $table->string('signature_status', 50)->nullable()->after('status');
            }

            if (! Schema::hasColumn('order_documents', 'requires_counterparty_signature')) {
                $table->boolean('requires_counterparty_signature')->default(false)->after('signature_status');
            }

            if (! Schema::hasColumn('order_documents', 'internal_signed_at')) {
                $table->timestamp('internal_signed_at')->nullable()->after('rejection_reason');
            }

            if (! Schema::hasColumn('order_documents', 'internal_signed_by')) {
                $table->foreignId('internal_signed_by')->nullable()->after('internal_signed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('order_documents', 'internal_signed_file_path')) {
                $table->string('internal_signed_file_path')->nullable()->after('internal_signed_by');
            }

            if (! Schema::hasColumn('order_documents', 'counterparty_signed_at')) {
                $table->timestamp('counterparty_signed_at')->nullable()->after('internal_signed_file_path');
            }

            if (! Schema::hasColumn('order_documents', 'counterparty_signed_file_path')) {
                $table->string('counterparty_signed_file_path')->nullable()->after('counterparty_signed_at');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('order_documents')) {
            Schema::table('order_documents', function (Blueprint $table): void {
                if (Schema::hasColumn('order_documents', 'internal_signed_by')) {
                    $table->dropConstrainedForeignId('internal_signed_by');
                }
            });
        }
    }
};
