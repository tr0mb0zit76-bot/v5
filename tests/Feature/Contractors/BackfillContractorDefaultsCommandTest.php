<?php

namespace Tests\Feature\Contractors;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillContractorDefaultsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('orders');
        Schema::dropIfExists('contractors');

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('default_customer_payment_form')->nullable();
            $table->string('default_customer_payment_term')->nullable();
            $table->string('default_carrier_payment_form')->nullable();
            $table->string('default_carrier_payment_term')->nullable();
            $table->text('cooperation_terms_notes')->nullable();
            $table->string('debt_limit_currency', 3)->nullable();
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('carrier_id')->nullable();
            $table->date('order_date')->nullable();
            $table->string('customer_payment_form')->nullable();
            $table->string('customer_payment_term')->nullable();
            $table->string('carrier_payment_form')->nullable();
            $table->string('carrier_payment_term')->nullable();
            $table->text('payment_terms')->nullable();
            $table->text('special_notes')->nullable();
            $table->timestamps();
        });
    }

    public function test_command_backfills_missing_defaults_from_existing_orders(): void
    {
        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'Logistics Test LLC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'customer_id' => $contractorId,
            'order_date' => '2026-04-01',
            'customer_payment_form' => 'vat',
            'customer_payment_term' => '7 days OTTN',
            'payment_terms' => 'Work via EDI only',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'carrier_id' => $contractorId,
            'order_date' => '2026-04-02',
            'carrier_payment_form' => 'no_vat',
            'carrier_payment_term' => '50/50',
            'special_notes' => 'Pay after document review',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('legacy:backfill-contractor-defaults')
            ->assertExitCode(0);

        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'default_customer_payment_form' => 'vat',
            'default_customer_payment_term' => '7 days OTTN',
            'default_carrier_payment_form' => 'no_vat',
            'default_carrier_payment_term' => '50/50',
            'cooperation_terms_notes' => 'Work via EDI only',
            'debt_limit_currency' => 'RUB',
        ]);
    }

    public function test_dry_run_does_not_write_changes(): void
    {
        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'Dry Run LLC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert([
            'customer_id' => $contractorId,
            'order_date' => '2026-04-03',
            'customer_payment_form' => 'cash',
            'customer_payment_term' => '3 days',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('legacy:backfill-contractor-defaults --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'default_customer_payment_form' => null,
            'default_customer_payment_term' => null,
            'debt_limit_currency' => null,
        ]);
    }
}
