<?php

namespace Tests\Unit;

use App\Models\ImportCostPp1291Category;
use App\Models\ImportCostTnVedEntry;
use App\Services\ImportCost\EecTnVedSyncService;
use App\Services\ImportCost\Pp1291ReferenceSyncService;
use App\Services\LeadPrecalculationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LeadPrecalculationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureImportCostTables();
        $this->seedReferences();
    }

    public function test_calculates_multi_line_goods_and_service_totals(): void
    {
        $service = app(LeadPrecalculationService::class);

        $result = $service->calculate([
            'goods_lines' => [
                [
                    'id' => 'goods_1',
                    'tn_ved_code' => '8429.51',
                    'invoice_amount' => 100_000,
                    'currency' => 'USD',
                    'exchange_rate' => 100,
                    'freight_to_border' => 50_000,
                    'vehicle_age_years' => 1,
                    'include_utilization_fee' => true,
                ],
            ],
            'service_lines' => [
                [
                    'id' => 'service_1',
                    'kind' => 'logistics',
                    'title' => 'Логистика · Плечо 1',
                    'stage' => 'leg_1',
                    'amount' => 250_000,
                    'currency' => 'RUB',
                ],
            ],
        ]);

        $this->assertSame(13_859_500.0, (float) ($result['computed']['goods_total'] ?? 0));
        $this->assertSame(250_000.0, (float) ($result['computed']['services_total'] ?? 0));
        $this->assertSame(14_109_500.0, (float) ($result['computed']['grand_total'] ?? 0));
        $this->assertCount(1, $result['computed']['goods_lines'] ?? []);
        $this->assertCount(1, $result['computed']['service_lines'] ?? []);
    }

    public function test_calculate_includes_freight_allocation_in_computed(): void
    {
        $service = app(LeadPrecalculationService::class);

        $result = $service->calculate([
            'freight' => [
                'to_border_total' => 100_000,
                'after_border_total' => 0,
                'distribution_basis' => 'equal',
            ],
            'goods_lines' => [
                [
                    'id' => 'goods_1',
                    'tn_ved_code' => '8429.51',
                    'invoice_amount' => 100_000,
                    'currency' => 'USD',
                    'exchange_rate' => 100,
                    'vehicle_age_years' => 1,
                    'include_utilization_fee' => true,
                ],
                [
                    'id' => 'goods_2',
                    'tn_ved_code' => '8429.51',
                    'invoice_amount' => 100_000,
                    'currency' => 'USD',
                    'exchange_rate' => 100,
                    'vehicle_age_years' => 1,
                    'include_utilization_fee' => true,
                ],
            ],
            'service_lines' => [],
        ]);

        $this->assertCount(2, $result['computed']['freight_allocation'] ?? []);
        $this->assertSame(50_000, $result['computed']['freight_allocation'][0]['freight_to_border'] ?? null);
        $this->assertSame(50_000, $result['computed']['freight_allocation'][1]['freight_to_border'] ?? null);
    }

    public function test_builds_service_lines_from_performers(): void
    {
        $service = app(LeadPrecalculationService::class);

        $lines = $service->serviceLinesFromPerformers([
            ['stage' => 'leg_1', 'estimated_cost' => 80000],
            ['stage' => 'leg_2', 'estimated_cost' => 45000],
        ]);

        $this->assertCount(2, $lines);
        $this->assertSame('leg_1', $lines[0]['stage'] ?? null);
        $this->assertSame(80000.0, (float) ($lines[0]['amount'] ?? 0));
        $this->assertSame('logistics', $lines[0]['kind'] ?? null);
    }

    private function ensureImportCostTables(): void
    {
        if (! Schema::hasTable('import_cost_tn_ved_entries')) {
            Schema::create('import_cost_tn_ved_entries', function (Blueprint $table): void {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('code_display', 12);
                $table->string('label');
                $table->decimal('duty_percent', 8, 4)->default(0);
                $table->decimal('vat_percent', 8, 4)->default(22);
                $table->string('pp1291_category_key', 64)->nullable();
                $table->boolean('requires_utilization_fee')->default(true);
                $table->string('duty_source', 32)->default('config');
                $table->json('eec_payload')->nullable();
                $table->timestamp('eec_synced_at')->nullable();
                $table->json('kodtnved_payload')->nullable();
                $table->timestamp('kodtnved_synced_at')->nullable();
                $table->json('alta_payload')->nullable();
                $table->timestamp('alta_synced_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('import_cost_pp1291_categories')) {
            Schema::create('import_cost_pp1291_categories', function (Blueprint $table): void {
                $table->id();
                $table->string('key', 64)->unique();
                $table->string('name');
                $table->unsignedInteger('base_fee_rub')->default(150_000);
                $table->json('age_coefficients');
                $table->string('decree_reference', 120)->default('ПП РФ № 1291');
                $table->date('effective_from')->nullable();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('import_cost_reference_syncs')) {
            Schema::create('import_cost_reference_syncs', function (Blueprint $table): void {
                $table->id();
                $table->string('source', 32);
                $table->string('status', 32);
                $table->unsignedInteger('items_updated')->default(0);
                $table->text('message')->nullable();
                $table->json('meta')->nullable();
                $table->timestamp('synced_at');
                $table->timestamps();
            });
        }
    }

    private function seedReferences(): void
    {
        config([
            'import_cost_calculator.default_vat_percent' => 20,
            'import_cost_calculator.customs_processing_fee_tiers' => [
                ['max' => PHP_FLOAT_MAX, 'fee' => 1000],
            ],
        ]);

        app(Pp1291ReferenceSyncService::class)->sync();
        app(EecTnVedSyncService::class)->seedFromConfig();

        ImportCostTnVedEntry::query()->firstOrCreate(
            ['code' => '8429510000'],
            [
                'code_display' => '8429.51',
                'label' => 'Погрузчики фронтальные',
                'duty_percent' => 0,
                'vat_percent' => 20,
                'pp1291_category_key' => 'self_propelled',
                'requires_utilization_fee' => true,
                'duty_source' => 'config',
                'is_active' => true,
            ],
        );

        ImportCostPp1291Category::query()->firstOrCreate(
            ['key' => 'self_propelled'],
            [
                'name' => 'Самоходная техника',
                'base_fee_rub' => 150_000,
                'age_coefficients' => ['0' => 11.99, '1' => 11.99],
                'decree_reference' => 'ПП РФ № 1291',
            ],
        );
    }
}
