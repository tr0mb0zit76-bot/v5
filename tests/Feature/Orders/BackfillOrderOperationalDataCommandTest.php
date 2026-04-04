<?php

namespace Tests\Feature\Orders;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BackfillOrderOperationalDataCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('financial_terms');
        Schema::dropIfExists('order_documents');
        Schema::dropIfExists('orders');

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->date('order_date')->nullable();
            $table->decimal('customer_rate', 12, 2)->nullable();
            $table->string('customer_payment_form')->nullable();
            $table->string('customer_payment_term')->nullable();
            $table->text('payment_terms')->nullable();
            $table->decimal('carrier_rate', 12, 2)->nullable();
            $table->string('carrier_payment_form')->nullable();
            $table->string('carrier_payment_term')->nullable();
            $table->json('performers')->nullable();
            $table->decimal('kpi_percent', 5, 2)->nullable();
            $table->decimal('delta', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('financial_terms', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->decimal('client_price', 12, 2)->nullable();
            $table->string('client_currency', 3)->default('RUB');
            $table->string('client_payment_terms')->nullable();
            $table->json('contractors_costs')->nullable();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('margin', 12, 2)->default(0);
            $table->json('additional_costs')->nullable();
            $table->timestamps();
        });

        Schema::create('order_documents', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('type');
            $table->string('document_group')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->nullable();
            $table->string('signature_status')->nullable();
            $table->boolean('requires_counterparty_signature')->nullable();
            $table->string('generated_pdf_path')->nullable();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->unsignedBigInteger('signed_by')->nullable();
            $table->timestamp('internal_signed_at')->nullable();
            $table->string('internal_signed_file_path')->nullable();
            $table->timestamp('counterparty_signed_at')->nullable();
            $table->string('counterparty_signed_file_path')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });
    }

    public function test_command_backfills_financial_terms_and_document_workflow_fields(): void
    {
        $orderId = DB::table('orders')->insertGetId([
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
            'template_id' => 5,
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
        $this->assertStringContainsString('"contractor_id":12', $contractorsCosts);
        $this->assertStringContainsString('"amount":90000', $contractorsCosts);

        $this->assertDatabaseHas('order_documents', [
            'id' => $documentId,
            'document_group' => 'request',
            'source' => 'generated',
            'signature_status' => 'pending_signature',
            'requires_counterparty_signature' => true,
        ]);
    }

    public function test_dry_run_does_not_write_changes(): void
    {
        $orderId = DB::table('orders')->insertGetId([
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
