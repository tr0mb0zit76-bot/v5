<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('finance_documents');
        Schema::dropIfExists('payment_schedules');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('permissions')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->json('visibility_scopes')->nullable();
            $table->json('columns_config')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->string('order_number')->nullable();
            $table->date('order_date')->nullable();
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->decimal('carrier_rate', 12, 2)->nullable();
            $table->string('customer_payment_form', 50)->nullable();
            $table->string('carrier_payment_form', 50)->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('upd_number')->nullable();
            $table->string('upd_carrier_number')->nullable();
            $table->string('status', 50)->default('new');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('finance_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('document_type', 20);
            $table->string('status', 20)->default('draft');
            $table->string('number', 50)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->enum('party', ['customer', 'carrier']);
            $table->enum('type', ['prepayment', 'final']);
            $table->decimal('amount', 12, 2);
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function test_finance_hub_returns_invoices_upds_and_cash_flow_rows(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'visibility_areas' => json_encode(['dashboard', 'documents'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'own'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $otherManager = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Клиент',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'ИП Перевозчик',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'manager_id' => $manager->id,
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'order_number' => 'ORD-100',
            'order_date' => '2026-04-05',
            'customer_rate' => 120000,
            'carrier_rate' => 80000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'invoice_number' => 'INV-100',
            'upd_number' => 'UPD-100',
            'upd_carrier_number' => 'C-UPD-100',
            'status' => 'documents',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'manager_id' => $otherManager->id,
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'order_number' => 'ORD-200',
            'order_date' => '2026-04-06',
            'customer_rate' => 50000,
            'carrier_rate' => 30000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'no_vat',
            'invoice_number' => 'INV-200',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('finance_documents')->insert([
            'order_id' => $orderId,
            'document_type' => 'invoice',
            'status' => 'draft',
            'number' => 'INV-100',
            'amount' => 120000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'planned_date' => '2026-04-20',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->get(route('finance.index', ['section' => 'documents']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Index')
            ->where('summary.invoices_total', 1)
            ->where('summary.invoices_issued', 1)
            ->where('summary.upds_total', 1)
            ->where('summary.upds_ready', 1)
            ->where('summary.cash_flow_total', 1)
            ->where('summary.cash_flow_pending', 1)
            ->has('invoices', 1)
            ->has('upds', 1)
            ->has('cashFlowJournal', 1)
            ->has('documents', 1)
            ->where('active_submodule', 'documents')
            ->where('documents.0.document_type', 'invoice')
            ->has('orders')
            ->where('invoices.0.order_number', 'ORD-100')
            ->where('cashFlowJournal.0.direction', 'Нам')
        );
    }
}
