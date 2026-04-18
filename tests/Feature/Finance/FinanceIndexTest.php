<?php

namespace Tests\Feature\Finance;

use App\Models\User;
use Carbon\Carbon;
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

        Schema::dropIfExists('payment_schedules');
        Schema::dropIfExists('leg_contractor_assignments');
        Schema::dropIfExists('order_legs');
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
            $table->string('full_name')->nullable();
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

        Schema::create('order_legs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();
        });

        Schema::create('leg_contractor_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_leg_id');
            $table->unsignedBigInteger('contractor_id')->nullable();
            $table->unsignedBigInteger('assigned_by');
            $table->string('status', 20)->default('pending');
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('counterparty_id')->nullable();
            $table->unsignedBigInteger('parent_payment_id')->nullable();
            $table->boolean('is_partial')->default(false);
            $table->enum('party', ['customer', 'carrier']);
            $table->enum('type', ['prepayment', 'final']);
            $table->decimal('amount', 12, 2);
            $table->string('invoice_number', 120)->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->decimal('remaining_amount', 12, 2)->nullable();
            $table->date('planned_date')->nullable();
            $table->date('actual_date')->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    public function test_finance_hub_returns_cash_flow_journal_and_stats(): void
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

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'invoice_number' => 'СЧ-PS-1',
            'planned_date' => '2026-04-20',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->get(route('finance.index', ['section' => 'cashflow']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Index')
            ->where('summary.cash_flow_total', 1)
            ->where('summary.cash_flow_pending', 1)
            ->has('cashFlowJournal', 1)
            ->where('active_submodule', 'cashflow')
            ->where('cashFlowJournal.0.direction', 'Нам')
            ->where('cashFlowJournal.0.invoice_number', 'СЧ-PS-1')
            ->where('cash_flow_stats.receivables.total', 120000)
            ->where('cash_flow_stats.receivables.pending', 120000)
            ->where('cash_flow_stats.receivables.overdue', 0)
        );
    }

    public function test_cash_flow_stats_use_remaining_amount_when_partially_paid(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'visibility_areas' => json_encode(['dashboard', 'documents'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'all'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
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
            'manager_id' => $user->id,
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'order_number' => 'ORD-PART',
            'order_date' => '2026-04-05',
            'customer_rate' => 120000,
            'carrier_rate' => 80000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'status' => 'documents',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'paid_amount' => 70000,
            'remaining_amount' => 50000,
            'planned_date' => '2026-04-20',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('finance.index', ['section' => 'cashflow']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('cash_flow_stats.receivables.total', 50000)
            ->where('cash_flow_stats.receivables.pending', 50000)
        );
    }

    public function test_cash_flow_journal_excludes_paid_root_rows(): void
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
            'order_number' => 'ORD-ALL',
            'order_date' => '2026-04-05',
            'customer_rate' => 120000,
            'carrier_rate' => 80000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'status' => 'documents',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'paid_amount' => 120000,
            'remaining_amount' => 0,
            'planned_date' => '2026-04-20',
            'actual_date' => '2026-04-18',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('finance.index', ['section' => 'cashflow']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('cashFlowJournal', 0)
            );
    }

    public function test_cash_flow_stats_use_full_amount_when_remaining_amount_is_null(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'visibility_areas' => json_encode(['dashboard', 'documents'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'all'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
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
            'manager_id' => $user->id,
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'order_number' => 'ORD-NULL-REM',
            'order_date' => '2026-04-05',
            'customer_rate' => 120000,
            'carrier_rate' => 80000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'status' => 'documents',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'paid_amount' => null,
            'remaining_amount' => null,
            'planned_date' => '2026-04-20',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->get(route('finance.index', ['section' => 'cashflow']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('cash_flow_stats.receivables.total', 120000)
                ->where('cash_flow_stats.receivables.pending', 120000)
            );
    }

    public function test_admin_can_patch_payment_schedule_invoice_number(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'visibility_areas' => json_encode(['dashboard', 'documents', 'finance_salary'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'all'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
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
            'manager_id' => $user->id,
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'order_number' => 'ORD-INV',
            'order_date' => '2026-04-05',
            'customer_rate' => 120000,
            'carrier_rate' => 80000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'status' => 'documents',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentScheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'planned_date' => '2026-04-20',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user)->patchJson(route('payment-schedules.invoice-number', $paymentScheduleId), [
            'invoice_number' => 'СЧ-999',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(
            'СЧ-999',
            DB::table('payment_schedules')->where('id', $paymentScheduleId)->value('invoice_number')
        );
    }

    public function test_cash_flow_journal_shows_carrier_from_leg_assignment_when_order_carrier_id_is_null(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'visibility_areas' => json_encode(['dashboard', 'documents'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'all'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Клиент',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => 'ИП Перевозчик из плеча',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'manager_id' => $user->id,
            'customer_id' => $customerId,
            'carrier_id' => null,
            'order_number' => 'ORD-LEG',
            'order_date' => '2026-04-05',
            'customer_rate' => 100000,
            'carrier_rate' => 70000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legId = DB::table('order_legs')->insertGetId([
            'order_id' => $orderId,
            'sequence' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('leg_contractor_assignments')->insert([
            'order_leg_id' => $legId,
            'contractor_id' => $carrierId,
            'assigned_by' => $user->id,
            'status' => 'confirmed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 70000,
            'planned_date' => '2026-04-25',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('finance.index', ['section' => 'cashflow']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('cashFlowJournal.0.counterparty_name', 'ИП Перевозчик из плеча')
            ->where('cashFlowJournal.0.direction', 'Мы')
        );
    }

    public function test_cash_flow_journal_uses_contractor_full_name_when_short_name_is_empty(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'visibility_areas' => json_encode(['dashboard', 'documents'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'all'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Клиент',
            'full_name' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $carrierId = DB::table('contractors')->insertGetId([
            'name' => '',
            'full_name' => 'ООО Новый перевозчик Полное имя',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'manager_id' => $user->id,
            'customer_id' => $customerId,
            'carrier_id' => $carrierId,
            'order_number' => 'ORD-FULL',
            'order_date' => '2026-04-05',
            'customer_rate' => 100000,
            'carrier_rate' => 70000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 70000,
            'planned_date' => '2026-04-25',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('finance.index', ['section' => 'cashflow']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('cashFlowJournal.0.counterparty_name', 'ООО Новый перевозчик Полное имя')
        );
    }

    public function test_cash_flow_journal_uses_counterparty_id_for_carrier_row_when_set(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'admin',
            'visibility_areas' => json_encode(['dashboard', 'documents'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'all'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Клиент',
            'full_name' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $oldCarrierId = DB::table('contractors')->insertGetId([
            'name' => 'Первый в заказе ТК',
            'full_name' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $newCarrierId = DB::table('contractors')->insertGetId([
            'name' => 'Второй по строке графика ТК',
            'full_name' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'manager_id' => $user->id,
            'customer_id' => $customerId,
            'carrier_id' => $oldCarrierId,
            'order_number' => 'ORD-SPLIT',
            'order_date' => '2026-04-05',
            'customer_rate' => 100000,
            'carrier_rate' => 70000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'counterparty_id' => $newCarrierId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 70000,
            'planned_date' => '2026-04-25',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('finance.index', ['section' => 'cashflow']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('cashFlowJournal.0.counterparty_name', 'Второй по строке графика ТК')
        );
    }

    public function test_cash_flow_journal_excludes_paid_payment_schedules(): void
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
            'order_number' => 'ORD-PAID',
            'order_date' => '2026-04-05',
            'customer_rate' => 120000,
            'carrier_rate' => 80000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'status' => 'documents',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('payment_schedules')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 120000,
            'planned_date' => '2026-04-20',
            'actual_date' => '2026-04-18',
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->get(route('finance.index', ['section' => 'cashflow']));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->has('cashFlowJournal', 0)
            ->where('summary.cash_flow_total', 0)
        );
    }

    public function test_cash_flow_journal_marks_overdue_after_planned_date_passes(): void
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
            'order_number' => 'ORD-OVD',
            'order_date' => '2026-04-05',
            'customer_rate' => 120000,
            'carrier_rate' => 80000,
            'customer_payment_form' => 'vat',
            'carrier_payment_form' => 'vat',
            'status' => 'documents',
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

        Carbon::setTestNow('2026-04-21 12:00:00');

        try {
            $response = $this->actingAs($manager)->get(route('finance.index', ['section' => 'cashflow']));

            $response->assertOk();
            $response->assertInertia(fn (Assert $page) => $page
                ->has('cashFlowJournal', 1)
                ->where('cashFlowJournal.0.status', 'overdue')
            );
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_legacy_documents_section_redirects_to_finance_overview(): void
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'visibility_areas' => json_encode(['documents'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'own'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($manager)
            ->get(route('finance.index', ['section' => 'documents']))
            ->assertRedirect(route('finance.index'));
    }

    public function test_manager_cannot_mutate_payment_schedule_actions(): void
    {
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'visibility_areas' => json_encode(['dashboard', 'documents'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'own'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = User::factory()->create([
            'role_id' => $managerRoleId,
            'email_verified_at' => now(),
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'manager_id' => $manager->id,
            'order_number' => 'ORD-LOCK',
            'order_date' => '2026-04-05',
            'status' => 'new',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paymentScheduleId = DB::table('payment_schedules')->insertGetId([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 1000,
            'planned_date' => '2026-04-20',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($manager)->post(route('payment-schedules.cancel', $paymentScheduleId));

        $response->assertForbidden();
    }
}
