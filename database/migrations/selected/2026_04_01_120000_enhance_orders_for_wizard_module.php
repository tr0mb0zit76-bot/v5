<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'own_company_id')) {
                    $table->foreignId('own_company_id')->nullable()->after('customer_id')->constrained('contractors')->nullOnDelete();
                }

                if (! Schema::hasColumn('orders', 'performers')) {
                    $table->json('performers')->nullable()->after('manager_id');
                }

                if (! Schema::hasColumn('orders', 'payment_terms')) {
                    $table->text('payment_terms')->nullable()->after('customer_payment_term');
                }

                if (! Schema::hasColumn('orders', 'special_notes')) {
                    $table->text('special_notes')->nullable()->after('payment_terms');
                }

                if (! Schema::hasColumn('orders', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }

        if (Schema::hasTable('route_points')) {
            Schema::table('route_points', function (Blueprint $table) {
                if (! Schema::hasColumn('route_points', 'address')) {
                    $table->string('address', 500)->nullable()->after('sequence');
                }

                if (! Schema::hasColumn('route_points', 'normalized_data')) {
                    $table->json('normalized_data')->nullable()->after('address');
                }
            });
        }

        if (Schema::hasTable('cargos')) {
            Schema::table('cargos', function (Blueprint $table) {
                if (! Schema::hasColumn('cargos', 'order_id')) {
                    $table->foreignId('order_id')->nullable()->after('id')->constrained('orders')->cascadeOnDelete();
                }

                if (! Schema::hasColumn('cargos', 'hs_code')) {
                    $table->string('hs_code', 50)->nullable()->after('hazard_class');
                }

                if (! Schema::hasColumn('cargos', 'package_count')) {
                    $table->unsignedInteger('package_count')->nullable()->after('packing_type');
                }
            });
        }

        if (Schema::hasTable('order_documents')) {
            Schema::table('order_documents', function (Blueprint $table) {
                if (! Schema::hasColumn('order_documents', 'number')) {
                    $table->string('number')->nullable()->after('type');
                }

                if (! Schema::hasColumn('order_documents', 'document_date')) {
                    $table->date('document_date')->nullable()->after('number');
                }

                if (! Schema::hasColumn('order_documents', 'generated_pdf_path')) {
                    $table->string('generated_pdf_path')->nullable()->after('file_path');
                }

                if (! Schema::hasColumn('order_documents', 'template_id')) {
                    $table->unsignedBigInteger('template_id')->nullable()->after('generated_pdf_path');
                }

                if (! Schema::hasColumn('order_documents', 'status')) {
                    $table->string('status', 50)->default('draft')->after('template_id');
                }

                if (! Schema::hasColumn('order_documents', 'signed_at')) {
                    $table->timestamp('signed_at')->nullable()->after('status');
                }

                if (! Schema::hasColumn('order_documents', 'signed_by')) {
                    $table->foreignId('signed_by')->nullable()->after('signed_at')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('financial_terms') === false && Schema::hasTable('orders')) {
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
        }

        if (Schema::hasTable('order_status_logs') === false && Schema::hasTable('orders')) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');
        Schema::dropIfExists('financial_terms');

        if (Schema::hasTable('order_documents')) {
            Schema::table('order_documents', function (Blueprint $table) {
                if (Schema::hasColumn('order_documents', 'signed_by')) {
                    $table->dropConstrainedForeignId('signed_by');
                }

                $columns = array_values(array_filter([
                    Schema::hasColumn('order_documents', 'number') ? 'number' : null,
                    Schema::hasColumn('order_documents', 'document_date') ? 'document_date' : null,
                    Schema::hasColumn('order_documents', 'generated_pdf_path') ? 'generated_pdf_path' : null,
                    Schema::hasColumn('order_documents', 'template_id') ? 'template_id' : null,
                    Schema::hasColumn('order_documents', 'status') ? 'status' : null,
                    Schema::hasColumn('order_documents', 'signed_at') ? 'signed_at' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('cargos')) {
            Schema::table('cargos', function (Blueprint $table) {
                if (Schema::hasColumn('cargos', 'order_id')) {
                    $table->dropConstrainedForeignId('order_id');
                }

                $columns = array_values(array_filter([
                    Schema::hasColumn('cargos', 'hs_code') ? 'hs_code' : null,
                    Schema::hasColumn('cargos', 'package_count') ? 'package_count' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('route_points')) {
            Schema::table('route_points', function (Blueprint $table) {
                $columns = array_values(array_filter([
                    Schema::hasColumn('route_points', 'address') ? 'address' : null,
                    Schema::hasColumn('route_points', 'normalized_data') ? 'normalized_data' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (Schema::hasColumn('orders', 'own_company_id')) {
                    $table->dropConstrainedForeignId('own_company_id');
                }

                $columns = array_values(array_filter([
                    Schema::hasColumn('orders', 'performers') ? 'performers' : null,
                    Schema::hasColumn('orders', 'payment_terms') ? 'payment_terms' : null,
                    Schema::hasColumn('orders', 'special_notes') ? 'special_notes' : null,
                ]));

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }

                if (Schema::hasColumn('orders', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }
    }
};
