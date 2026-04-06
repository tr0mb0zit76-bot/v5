<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractors')) {
            return;
        }

        Schema::table('contractors', function (Blueprint $table): void {
            if (! Schema::hasColumn('contractors', 'debt_limit')) {
                $table->decimal('debt_limit', 12, 2)->nullable()->after('metadata');
            }

            if (! Schema::hasColumn('contractors', 'debt_limit_currency')) {
                $table->string('debt_limit_currency', 3)->default('RUB')->after('debt_limit');
            }

            if (! Schema::hasColumn('contractors', 'stop_on_limit')) {
                $table->boolean('stop_on_limit')->default(false)->after('debt_limit_currency');
            }

            if (! Schema::hasColumn('contractors', 'default_customer_payment_form')) {
                $table->string('default_customer_payment_form', 50)->nullable()->after('stop_on_limit');
            }

            if (! Schema::hasColumn('contractors', 'default_customer_payment_term')) {
                $table->string('default_customer_payment_term')->nullable()->after('default_customer_payment_form');
            }

            if (! Schema::hasColumn('contractors', 'default_carrier_payment_form')) {
                $table->string('default_carrier_payment_form', 50)->nullable()->after('default_customer_payment_term');
            }

            if (! Schema::hasColumn('contractors', 'default_carrier_payment_term')) {
                $table->string('default_carrier_payment_term')->nullable()->after('default_carrier_payment_form');
            }

            if (! Schema::hasColumn('contractors', 'cooperation_terms_notes')) {
                $table->text('cooperation_terms_notes')->nullable()->after('default_carrier_payment_term');
            }
        });

        if (! Schema::hasTable('contractor_contacts')) {
            Schema::create('contractor_contacts', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
                $table->string('full_name');
                $table->string('position')->nullable();
                $table->string('phone', 50)->nullable();
                $table->string('email')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contractor_interactions')) {
            Schema::create('contractor_interactions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
                $table->timestamp('contacted_at')->nullable();
                $table->string('channel', 50)->nullable();
                $table->string('subject')->nullable();
                $table->text('summary')->nullable();
                $table->string('result')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contractor_documents')) {
            Schema::create('contractor_documents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
                $table->string('type')->nullable();
                $table->string('title');
                $table->string('number')->nullable();
                $table->date('document_date')->nullable();
                $table->string('status')->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contractor_documents')) {
            Schema::drop('contractor_documents');
        }

        if (Schema::hasTable('contractor_interactions')) {
            Schema::drop('contractor_interactions');
        }

        if (Schema::hasTable('contractor_contacts')) {
            Schema::drop('contractor_contacts');
        }
    }
};
