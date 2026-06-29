<?php

namespace Tests\Feature\Contractors;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillContractorDefaultsCommandTest extends TestCase
{
    public function test_command_backfills_missing_defaults_from_existing_orders(): void
    {
        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'Logistics Test LLC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertOrderRow([
            'customer_id' => $contractorId,
            'order_date' => '2026-04-01',
            'customer_payment_form' => 'vat',
            'customer_payment_term' => '7 days OTTN',
            'special_notes' => 'Work via EDI only',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->insertOrderRow([
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
            'cooperation_terms_notes' => 'Work via EDI only',
            'debt_limit_currency' => 'RUB',
        ]);

        if (Schema::hasColumn('orders', 'carrier_payment_form')) {
            $this->assertDatabaseHas('contractors', [
                'id' => $contractorId,
                'default_carrier_payment_form' => 'no_vat',
                'default_carrier_payment_term' => '50/50',
            ]);
        }
    }

    public function test_dry_run_does_not_write_changes(): void
    {
        $contractorId = DB::table('contractors')->insertGetId([
            'name' => 'Dry Run LLC',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('orders')->insert($this->onlyExistingOrderColumns([
            'customer_id' => $contractorId,
            'order_date' => '2026-04-03',
            'customer_payment_form' => 'cash',
            'customer_payment_term' => '3 days',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $this->artisan('legacy:backfill-contractor-defaults --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseHas('contractors', [
            'id' => $contractorId,
            'default_customer_payment_form' => null,
            'default_customer_payment_term' => null,
        ]);
    }
}
