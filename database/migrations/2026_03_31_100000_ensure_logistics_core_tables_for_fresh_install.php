<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * На чистой установке legacy create_all_tables / create_logist_v5_plus_schema не создают
     * contractors / orders / order_legs (ранний return), из-за чего ломается порядок FK
     * (leg_contractor_assignments → order_legs на MySQL). Поднимаем минимальное ядро логистики.
     */
    public function up(): void
    {
        if (Schema::hasTable('order_legs')) {
            return;
        }

        if (! Schema::hasTable('contractors')) {
            Schema::create('contractors', function (Blueprint $table) {
                $table->id();

                $table->enum('type', ['customer', 'carrier', 'both'])->default('both');

                $table->string('name')->index();
                $table->string('full_name')->nullable();

                $table->string('inn', 20)->nullable()->index();
                $table->string('kpp', 20)->nullable();
                $table->string('ogrn', 20)->nullable();
                $table->string('okpo', 20)->nullable();

                $table->enum('legal_form', ['ooo', 'zao', 'ao', 'ip', 'samozanyaty', 'other'])->nullable();

                $table->string('legal_address')->nullable();
                $table->string('actual_address')->nullable();
                $table->string('postal_address')->nullable();

                $table->string('phone', 50)->nullable()->index();
                $table->string('email')->nullable()->index();
                $table->string('website')->nullable();

                $table->string('contact_person')->nullable();
                $table->string('contact_person_phone', 50)->nullable();
                $table->string('contact_person_email')->nullable();
                $table->string('contact_person_position')->nullable();
                $table->string('signer_position')->nullable();

                $table->string('bank_name')->nullable();
                $table->string('bik', 9)->nullable();
                $table->string('account_number', 20)->nullable();
                $table->string('correspondent_account', 20)->nullable();

                $table->json('ati_profiles')->nullable();
                $table->string('ati_id', 50)->nullable();
                $table->json('transport_requirements')->nullable();
                $table->json('specializations')->nullable();

                $table->decimal('rating', 3, 2)->default(0);
                $table->integer('completed_orders')->default(0);

                $table->json('metadata')->nullable();

                $table->boolean('is_active')->default(true)->index();
                $table->boolean('is_verified')->default(false);

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();

                $table->index(['type', 'is_active'], 'contractors_type_is_active_index');
            });
        }

        if (! Schema::hasTable('drivers')) {
            Schema::create('drivers', function (Blueprint $table) {
                $table->id();

                $table->string('first_name');
                $table->string('last_name');
                $table->string('patronymic')->nullable();

                $table->string('phone', 50)->nullable()->index();
                $table->string('email')->nullable();

                $table->string('license_number')->nullable();
                $table->date('license_expiry')->nullable();

                $table->foreignId('contractor_id')->nullable()->constrained('contractors')->nullOnDelete();

                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);

                $table->timestamps();

                $table->index('contractor_id');
            });
        }

        if (! Schema::hasTable('cargos')) {
            Schema::create('cargos', function (Blueprint $table) {
                $table->id();

                $table->string('title', 500)->index();
                $table->text('description')->nullable();

                $table->decimal('weight', 10, 2)->nullable()->index();
                $table->decimal('volume', 10, 2)->nullable();

                $table->string('cargo_type', 100)->nullable();
                $table->unsignedInteger('cargo_type_id')->nullable();

                $table->string('packing_type', 100)->nullable();
                $table->unsignedInteger('pack_type_id')->nullable();

                $table->integer('pallet_count')->nullable();
                $table->integer('belt_count')->nullable();

                $table->decimal('length', 10, 2)->nullable();
                $table->decimal('width', 10, 2)->nullable();
                $table->decimal('height', 10, 2)->nullable();

                $table->boolean('is_hazardous')->default(false);
                $table->string('hazard_class', 10)->nullable();

                $table->boolean('needs_temperature')->default(false);
                $table->decimal('temp_min', 5, 2)->nullable();
                $table->decimal('temp_max', 5, 2)->nullable();

                $table->boolean('needs_hydraulic')->default(false);
                $table->boolean('needs_manipulator')->default(false);

                $table->text('special_instructions')->nullable();

                $table->json('photos')->nullable();
                $table->json('documents')->nullable();

                $table->unsignedBigInteger('ati_load_id')->nullable()->index();
                $table->timestamp('ati_published_at')->nullable();
                $table->json('ati_response')->nullable();

                $table->text('source_text')->nullable();
                $table->string('source_file', 500)->nullable();
                $table->boolean('parsed_by_ai')->default(false);
                $table->timestamp('parsed_at')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                $table->timestamps();
            });
        }

        if (! Schema::hasTable('orders')) {
            Schema::create('orders', function (Blueprint $table) {
                $table->id();

                $table->string('order_number')->nullable()->index();
                $table->string('company_code', 10)->nullable()->index();

                $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();

                $table->date('order_date')->nullable()->index();
                $table->date('loading_date')->nullable()->index();
                $table->date('unloading_date')->nullable()->index();

                $table->decimal('customer_rate', 12, 2)->nullable();
                $table->string('customer_payment_form', 50)->nullable();
                $table->string('customer_payment_term', 50)->nullable();

                $table->decimal('carrier_rate', 12, 2)->nullable();
                $table->string('carrier_payment_form', 50)->nullable();
                $table->string('carrier_payment_term', 50)->nullable();

                $table->decimal('additional_expenses', 12, 2)->default(0);
                $table->decimal('insurance', 12, 2)->default(0);
                $table->decimal('bonus', 12, 2)->default(0);

                $table->decimal('kpi_percent', 5, 2)->nullable();
                $table->decimal('delta', 12, 2)->nullable();
                $table->decimal('salary_accrued', 12, 2)->default(0);
                $table->decimal('salary_paid', 12, 2)->default(0);

                $table->string('status', 50)->default('new')->index();
                $table->string('manual_status', 50)->nullable();
                $table->foreignId('status_updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('status_updated_at')->nullable();

                $table->boolean('is_active')->default(true);

                $table->foreignId('customer_id')->nullable()->constrained('contractors')->nullOnDelete();
                $table->foreignId('carrier_id')->nullable()->constrained('contractors')->nullOnDelete();
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->nullOnDelete();

                $table->unsignedBigInteger('ai_draft_id')->nullable()->index();
                $table->decimal('ai_confidence', 5, 2)->nullable();
                $table->json('ai_metadata')->nullable();
                $table->json('ati_response')->nullable();
                $table->string('ati_load_id')->nullable()->unique();
                $table->timestamp('ati_published_at')->nullable();

                $table->string('invoice_number')->nullable();
                $table->string('upd_number')->nullable();
                $table->string('waybill_number')->nullable();

                $table->string('track_number_customer')->nullable();
                $table->date('track_sent_date_customer')->nullable();
                $table->date('track_received_date_customer')->nullable();

                $table->string('track_number_carrier')->nullable();
                $table->date('track_sent_date_carrier')->nullable();
                $table->date('track_received_date_carrier')->nullable();

                $table->string('order_customer_number')->nullable();
                $table->date('order_customer_date')->nullable();

                $table->string('order_carrier_number')->nullable();
                $table->date('order_carrier_date')->nullable();

                $table->string('upd_carrier_number')->nullable();
                $table->date('upd_carrier_date')->nullable();

                $table->string('customer_contact_name')->nullable();
                $table->string('customer_contact_phone', 50)->nullable();
                $table->string('customer_contact_email')->nullable();

                $table->string('carrier_contact_name')->nullable();
                $table->string('carrier_contact_phone', 50)->nullable();
                $table->string('carrier_contact_email')->nullable();

                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

                $table->json('metadata')->nullable();
                $table->json('payment_statuses')->nullable();

                $table->timestamps();

                $table->index(['manager_id', 'order_date'], 'orders_manager_id_order_date_index');
                $table->index(['status', 'is_active'], 'orders_status_is_active_index');
            });
        }

        Schema::create('order_legs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->integer('sequence')->default(0);
            $table->enum('type', ['transport', 'storage', 'transshipment'])->default('transport');
            $table->string('description', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'sequence'], 'order_legs_order_id_sequence_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_legs')) {
            return;
        }

        Schema::dropIfExists('order_legs');

        if (Schema::hasTable('orders')) {
            Schema::dropIfExists('orders');
        }

        if (Schema::hasTable('cargos')) {
            Schema::dropIfExists('cargos');
        }

        if (Schema::hasTable('drivers')) {
            Schema::dropIfExists('drivers');
        }

        if (Schema::hasTable('contractors')) {
            Schema::dropIfExists('contractors');
        }
    }
};
