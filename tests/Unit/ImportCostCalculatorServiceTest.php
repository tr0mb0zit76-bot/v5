<?php

namespace Tests\Unit;

use App\Models\ImportCostPp1291Category;
use App\Models\ImportCostTnVedEntry;
use App\Services\ImportCost\EecTnVedSyncService;
use App\Services\ImportCost\Pp1291ReferenceSyncService;
use App\Services\ImportCostCalculatorService;
use App\Support\ImportCostTnVedCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ImportCostCalculatorServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureImportCostTables();
        $this->seedReferences();
    }

    public function test_calculates_landed_cost_with_duty_vat_and_pp1291_utilization_fee(): void
    {
        $service = app(ImportCostCalculatorService::class);

        $result = $service->calculate([
            'invoice_amount' => 100_000,
            'currency' => 'USD',
            'exchange_rate' => 100,
            'tn_ved_code' => '8429.52',
            'freight_to_border' => 50_000,
            'vehicle_age_years' => 1,
            'include_utilization_fee' => true,
        ]);

        $this->assertArrayHasKey('summary', $result);
        $this->assertSame(10_050_000.0, $result['summary']['customs_value']);
        $this->assertSame(0.0, $this->amountByKey($result['breakdown'], 'duty'));
        $this->assertSame(2_010_000.0, $this->amountByKey($result['breakdown'], 'vat'));
        $this->assertSame(1_798_500.0, $this->amountByKey($result['breakdown'], 'utilization_fee'));
        $this->assertSame(13_859_500.0, $result['summary']['total_landed']);
    }

    public function test_pp1291_sync_loads_categories_into_database(): void
    {
        $result = app(Pp1291ReferenceSyncService::class)->sync();

        $this->assertSame('success', $result['status']);
        $this->assertGreaterThan(0, ImportCostPp1291Category::query()->count());
    }

    public function test_eec_sync_updates_duty_from_odata_payload(): void
    {
        Http::fake([
            '*MetadataList*' => Http::response([
                'd' => [
                    'results' => [
                        ['MetadataList_title_name' => 'ЕТТ ставки ТН ВЭД'],
                    ],
                ],
            ], 200),
            '*%D0%95%D0%A2%D0%A2*' => Http::response([
                'd' => [
                    'results' => [
                        [
                            'Code' => '8429520000',
                            'Name' => 'Погрузчики фронтальные',
                            'ImportDuty' => '7.5',
                        ],
                    ],
                ],
            ], 200),
            '*' => Http::response(['d' => ['results' => []]], 200),
        ]);

        $result = app(EecTnVedSyncService::class)->sync();

        $this->assertContains($result['status'], ['success', 'partial']);
        $entry = ImportCostTnVedEntry::query()->where('code', '8429520000')->first();
        $this->assertNotNull($entry);
        $this->assertSame(7.5, (float) $entry->duty_percent);
        $this->assertSame('eec', $entry->duty_source);
    }

    public function test_returns_warning_when_invoice_missing(): void
    {
        $service = app(ImportCostCalculatorService::class);

        $result = $service->calculate([
            'tn_ved_code' => ImportCostTnVedCatalog::all()[0]['code'],
        ]);

        $this->assertSame('Укажите инвойсную стоимость.', $result['warning'] ?? null);
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
    }

    /**
     * @param  list<array{key: string, amount: float}>  $breakdown
     */
    private function amountByKey(array $breakdown, string $key): float
    {
        foreach ($breakdown as $row) {
            if (($row['key'] ?? '') === $key) {
                return (float) $row['amount'];
            }
        }

        $this->fail('Breakdown row '.$key.' not found');
    }
}
