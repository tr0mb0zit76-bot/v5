<?php

namespace Tests\Feature\Orders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillOrderOperationalDataCommandTest extends TestCase
{
    public function test_command_backfills_financial_terms_and_document_workflow_fields(): void
    {
        $orderId = $this->insertOrderRow([
            'order_date' => '2026-04-04',
            'customer_rate' => 150000,
            'customer_payment_form' => 'vat',
            'customer_payment_term' => '7 days OTTN',
            'carrier_rate' => 90000,
            'carrier_payment_form' => 'no_vat',
            'carrier_payment_term' => '5 days OTTN',
            'performers' => json_encode([
                ['stage' => 'leg_1', 'contractor_id' => 12],
            ], JSON_THROW_ON_ERROR),
            'delta' => 35000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $documentId = DB::table('order_documents')->insertGetId([
            'order_id' => $orderId,
            'type' => 'request',
            'status' => 'sent',
            'template_id' => null,
            'generated_pdf_path' => 'generated/request.pdf',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('legacy:backfill-order-operations')
            ->assertExitCode(0);

        $this->assertDatabaseHas('financial_terms', [
            'order_id' => $orderId,
            'client_price' => '150000.00',
            'client_currency' => 'RUB',
            'client_payment_terms' => '7 days OTTN',
            'margin' => '35000.00',
        ]);

        $contractorsCosts = DB::table('financial_terms')
            ->where('order_id', $orderId)
            ->value('contractors_costs');

        $this->assertIsString($contractorsCosts);

        if (Schema::hasColumn('orders', 'performers') || Schema::hasColumn('orders', 'carrier_rate')) {
            $this->assertStringContainsString('"amount":90000', $contractorsCosts);

            if (Schema::hasColumn('orders', 'performers')) {
                $this->assertStringContainsString('"contractor_id":12', $contractorsCosts);
            }
        }

        $this->assertDatabaseHas('order_documents', [
            'id' => $documentId,
            'document_group' => 'request',
            'requires_counterparty_signature' => true,
        ]);

        $document = DB::table('order_documents')->where('id', $documentId)->first();
        $this->assertNotNull($document?->source);
        $this->assertNotNull($document?->signature_status);
    }

    public function test_dry_run_does_not_write_changes(): void
    {
        $orderId = $this->insertOrderRow([
            'customer_rate' => 100000,
            'carrier_rate' => 70000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('legacy:backfill-order-operations --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('financial_terms', [
            'order_id' => $orderId,
        ]);
    }
}
