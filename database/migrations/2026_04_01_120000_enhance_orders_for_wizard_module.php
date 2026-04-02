<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('own_company_id')->nullable()->after('customer_id')->constrained('contractors')->nullOnDelete();
            $table->json('performers')->nullable()->after('manager_id');
            $table->text('payment_terms')->nullable()->after('customer_payment_term');
            $table->text('special_notes')->nullable()->after('payment_terms');
            $table->softDeletes();
        });

        Schema::table('route_points', function (Blueprint $table) {
            $table->string('address', 500)->nullable()->after('sequence');
            $table->json('normalized_data')->nullable()->after('address');
        });

        Schema::table('cargos', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->after('id')->constrained('orders')->cascadeOnDelete();
            $table->string('hs_code', 50)->nullable()->after('hazard_class');
            $table->unsignedInteger('package_count')->nullable()->after('packing_type');
        });

        Schema::table('order_documents', function (Blueprint $table) {
            $table->string('number')->nullable()->after('type');
            $table->date('document_date')->nullable()->after('number');
            $table->string('generated_pdf_path')->nullable()->after('file_path');
            $table->unsignedBigInteger('template_id')->nullable()->after('generated_pdf_path');
            $table->string('status', 50)->default('draft')->after('template_id');
            $table->timestamp('signed_at')->nullable()->after('status');
            $table->foreignId('signed_by')->nullable()->after('signed_at')->constrained('users')->nullOnDelete();

            $table->string('original_name')->nullable()->change();
            $table->string('file_path')->nullable()->change();
        });

        Schema::create('financial_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('client_price', 12, 2)->nullable();
            $table->string('client_currency', 3)->default('RUB');
            $table->string('client_payment_terms', 255)->nullable();
            $table->json('contractors_costs')->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('margin', 12, 2)->default(0);
            $table->json('additional_costs')->nullable();
            $table->timestamps();
        });

        Schema::create('order_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('status_from', 50)->nullable();
            $table->string('status_to', 50);
            $table->text('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('financial_terms');

        Schema::table('order_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('signed_by');
            $table->dropColumn([
                'number',
                'document_date',
                'generated_pdf_path',
                'template_id',
                'status',
                'signed_at',
            ]);
        });

        Schema::table('cargos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('order_id');
            $table->dropColumn(['hs_code', 'package_count']);
        });

        Schema::table('route_points', function (Blueprint $table) {
            $table->dropColumn(['address', 'normalized_data']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('own_company_id');
            $table->dropColumn(['performers', 'payment_terms', 'special_notes']);
            $table->dropSoftDeletes();
        });
    }
};
