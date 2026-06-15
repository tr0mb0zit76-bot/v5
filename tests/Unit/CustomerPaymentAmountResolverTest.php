<?php

namespace Tests\Unit;

use App\Support\CustomerPaymentAmountResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CustomerPaymentAmountResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['payment_schedule_payment_events', 'payment_schedules', 'orders']);
    }

    #[Test]
    public function sums_root_paid_amount_when_ledger_table_exists_but_has_no_events(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('payment_schedule_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('party', 20);
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_payment_id')->nullable();
            $table->string('party', 20);
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->boolean('is_partial')->default(false);
            $table->date('actual_date')->nullable();
            $table->string('status', 20);
            $table->timestamps();
        });

        DB::table('orders')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_schedules')->insert([
            'order_id' => 1,
            'party' => 'customer',
            'amount' => 2240000,
            'paid_amount' => 1120000,
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $paid = CustomerPaymentAmountResolver::paidForOrderUntil(1);

        $this->assertSame(1120000.0, $paid);
    }

    #[Test]
    public function ignores_reversed_ledger_events(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });

        Schema::create('payment_schedule_payment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('party', 20);
            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->unsignedBigInteger('parent_payment_id')->nullable();
            $table->string('party', 20);
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->boolean('is_partial')->default(false);
            $table->date('actual_date')->nullable();
            $table->string('status', 20);
            $table->timestamps();
        });

        DB::table('orders')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('payment_schedule_payment_events')->insert([
            [
                'order_id' => 1,
                'party' => 'customer',
                'amount' => 50000,
                'payment_date' => '2026-06-09',
                'reversed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'order_id' => 1,
                'party' => 'customer',
                'amount' => 567230.50,
                'payment_date' => '2026-06-10',
                'reversed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $paid = CustomerPaymentAmountResolver::paidForOrderUntil(1);

        $this->assertSame(567230.50, $paid);
    }
}
