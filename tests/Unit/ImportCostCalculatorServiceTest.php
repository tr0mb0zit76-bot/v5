<?php

namespace Tests\Unit;

use App\Models\ImportCostPp1291Category;
use App\Models\ImportCostTnVedEntry;
use App\Services\ImportCost\AltaReferenceSyncService;
use App\Services\ImportCost\EecTnVedSyncService;
use App\Services\ImportCost\KodTnVedReferenceSyncService;
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
            'tn_ved_code' => '8429.51',
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

    public function test_eec_sync_creates_new_matching_codes(): void
    {
        ImportCostTnVedEntry::query()->where('code', '8429529000')->delete();

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
                            'Code' => '8429529000',
                            'Name' => 'Прочие машины полноповоротные',
                            'ImportDuty' => '5',
                        ],
                    ],
                ],
            ], 200),
            '*' => Http::response(['d' => ['results' => []]], 200),
        ]);

        $this->assertNull(ImportCostTnVedEntry::query()->where('code', '8429529000')->first());

        app(EecTnVedSyncService::class)->sync();

        $entry = ImportCostTnVedEntry::query()->where('code', '8429529000')->first();
        $this->assertNotNull($entry);
        $this->assertSame(5.0, (float) $entry->duty_percent);
        $this->assertSame('eec', $entry->duty_source);
        $this->assertSame('crawler_excavator', $entry->pp1291_category_key);
    }

    public function test_alta_sync_skips_when_credentials_missing(): void
    {
        config([
            'import_cost_calculator.alta.login' => null,
            'import_cost_calculator.alta.password' => null,
        ]);

        $result = app(AltaReferenceSyncService::class)->sync(10);

        $this->assertSame('partial', $result['status']);
        $this->assertSame(0, $result['items_updated']);
        $this->assertStringContainsString('учётные данные не настроены', $result['message']);
    }

    public function test_alta_sync_updates_duty_from_xml(): void
    {
        ImportCostTnVedEntry::query()->updateOrCreate(
            ['code' => '8429529000'],
            [
                'code_display' => '8429.52.90',
                'label' => '8429529000',
                'duty_percent' => 0,
                'vat_percent' => 22,
                'pp1291_category_key' => 'crawler_excavator',
                'requires_utilization_fee' => true,
                'duty_source' => 'config',
                'alta_synced_at' => null,
                'is_active' => true,
            ],
        );

        $xml = file_get_contents(base_path('tests/Fixtures/alta-8429529000.xml'));
        $this->assertNotFalse($xml);

        config([
            'import_cost_calculator.alta.login' => 'crm-login',
            'import_cost_calculator.alta.password' => 'crm-password',
            'import_cost_calculator.alta.delay_ms' => 0,
        ]);

        Http::fake([
            'https://www.alta.ru/tnved/xml/*' => Http::response($xml, 200),
        ]);

        $result = app(AltaReferenceSyncService::class)->sync(10);

        $this->assertContains($result['status'], ['success', 'partial']);
        $entry = ImportCostTnVedEntry::query()->where('code', '8429529000')->first();
        $this->assertNotNull($entry);
        $this->assertSame(5.0, (float) $entry->duty_percent);
        $this->assertSame('alta', $entry->duty_source);
        $this->assertSame(22.0, (float) $entry->vat_percent);
    }

    public function test_kodtnved_sync_updates_zero_duty_from_html(): void
    {
        ImportCostTnVedEntry::query()->updateOrCreate(
            ['code' => '8429529000'],
            [
                'code_display' => '8429.52.90',
                'label' => '8429529000',
                'duty_percent' => 0,
                'vat_percent' => 22,
                'pp1291_category_key' => 'crawler_excavator',
                'requires_utilization_fee' => true,
                'duty_source' => 'config',
                'kodtnved_synced_at' => null,
                'is_active' => true,
            ],
        );

        $html = file_get_contents(base_path('tests/Fixtures/kodtnved-8429529000.html'));
        $this->assertNotFalse($html);

        Http::fake([
            'https://kodtnved.ru/ts/8429529000.html' => Http::response($html, 200),
        ]);

        config(['import_cost_calculator.kodtnved.delay_ms' => 0]);

        $result = app(KodTnVedReferenceSyncService::class)->sync(10);

        $this->assertContains($result['status'], ['success', 'partial']);
        $entry = ImportCostTnVedEntry::query()->where('code', '8429529000')->first();
        $this->assertNotNull($entry);
        $this->assertSame(5.0, (float) $entry->duty_percent);
        $this->assertSame('kodtnved', $entry->duty_source);
        $this->assertSame(20.0, (float) $entry->vat_percent);
    }

    public function test_kodtnved_does_not_override_alta_duty_source(): void
    {
        ImportCostTnVedEntry::query()->updateOrCreate(
            ['code' => '8429529000'],
            [
                'code_display' => '8429.52.90',
                'label' => 'Прочие машины полноповоротные',
                'duty_percent' => 5,
                'vat_percent' => 22,
                'pp1291_category_key' => 'crawler_excavator',
                'requires_utilization_fee' => true,
                'duty_source' => 'alta',
                'alta_synced_at' => now(),
                'kodtnved_synced_at' => null,
                'is_active' => true,
            ],
        );

        $html = file_get_contents(base_path('tests/Fixtures/kodtnved-8429529000.html'));
        $this->assertNotFalse($html);

        Http::fake([
            'https://kodtnved.ru/ts/8429529000.html' => Http::response($html, 200),
        ]);

        config(['import_cost_calculator.kodtnved.delay_ms' => 0]);

        app(KodTnVedReferenceSyncService::class)->sync(10);

        $entry = ImportCostTnVedEntry::query()->where('code', '8429529000')->first();
        $this->assertNotNull($entry);
        $this->assertSame('alta', $entry->duty_source);
        $this->assertSame(5.0, (float) $entry->duty_percent);
    }

    public function test_catalog_search_finds_codes_by_prefix(): void
    {
        $results = ImportCostTnVedCatalog::search('842952');

        $this->assertNotEmpty($results);
        $this->assertTrue(collect($results)->contains(fn (array $row): bool => str_starts_with($row['code'], '842952')));
    }

    public function test_coarse_code_flag_for_parent_tn_ved(): void
    {
        $this->assertTrue(ImportCostTnVedCatalog::isCoarseCode('8429520000'));
        $this->assertFalse(ImportCostTnVedCatalog::isCoarseCode('8429529000'));
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
                $table->json('kodtnved_payload')->nullable();
                $table->timestamp('kodtnved_synced_at')->nullable();
                $table->json('alta_payload')->nullable();
                $table->timestamp('alta_synced_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        } elseif (! Schema::hasColumn('import_cost_tn_ved_entries', 'alta_synced_at')) {
            Schema::table('import_cost_tn_ved_entries', function (Blueprint $table): void {
                if (! Schema::hasColumn('import_cost_tn_ved_entries', 'kodtnved_synced_at')) {
                    $table->json('kodtnved_payload')->nullable()->after('eec_synced_at');
                    $table->timestamp('kodtnved_synced_at')->nullable()->after('kodtnved_payload');
                }

                $table->json('alta_payload')->nullable()->after('kodtnved_synced_at');
                $table->timestamp('alta_synced_at')->nullable()->after('alta_payload');
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
