<?php

declare(strict_types=1);

namespace Tests\Unit\Services\OneC;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementLine;
use App\Models\Task;
use App\Models\User;
use App\Services\OneC\OneCBpClient;
use App\Services\OneC\OneCBridgeCheckService;
use App\Services\OneC\OneCBridgeEscalationService;
use App\Services\OneC\OneCBridgeHealthService;
use App\Services\OneC\OneCPublicationCatalog;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneCBridgeControlTest extends TestCase
{
    public function test_catalog_resolves_configured_publications(): void
    {
        config([
            'one_c.publications' => [
                'autalliance' => [
                    'label' => 'АА',
                    'base_url' => 'https://one-c.test/aa',
                    'organization_ref' => 'org-aa',
                    'bank_account_number' => '40702810959710001997',
                    'date_filter_mode' => 'odata',
                    'enabled' => true,
                ],
                'gross' => [
                    'label' => 'Гросс',
                    'base_url' => 'https://one-c.test/gross',
                    'organization_ref' => 'org-gross',
                    'bank_account_number' => '40702810629940001726',
                    'date_filter_mode' => 'client',
                    'enabled' => true,
                ],
            ],
        ]);

        $catalog = app(OneCPublicationCatalog::class);
        $this->assertCount(2, $catalog->all());
        $this->assertSame('client', $catalog->get('gross')['date_filter_mode']);
    }

    public function test_client_date_filter_mode_filters_in_php(): void
    {
        config([
            'one_c.driver' => 'http',
            'one_c.base_url' => 'https://one-c.test/gross',
            'one_c.username' => 'Odata',
            'one_c.password' => 'x',
            'one_c.odata.bank_incoming_path' => '/odata/standard.odata/Document_In',
            'one_c.odata.bank_outgoing_path' => '/odata/standard.odata/Document_Out',
        ]);

        Http::fake([
            'one-c.test/gross/odata/standard.odata/Document_In*' => Http::response([
                'value' => [
                    [
                        'Ref_Key' => 'in-old',
                        'Date' => '2026-07-01T00:00:00',
                        'Number' => '1',
                        'СуммаДокумента' => 10,
                        'Posted' => true,
                    ],
                    [
                        'Ref_Key' => 'in-ok',
                        'Date' => '2026-08-01T00:00:00',
                        'Number' => '2',
                        'СуммаДокумента' => 20,
                        'Posted' => true,
                    ],
                ],
            ], 200),
            'one-c.test/gross/odata/standard.odata/Document_Out*' => Http::response([
                'value' => [],
            ], 200),
        ]);

        $rows = app(OneCBpClient::class)->listBankMovements('2026-08-01', '2026-08-02', [
            'base_url' => 'https://one-c.test/gross',
            'organization_ref' => '',
            'date_filter_mode' => 'client',
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('in-ok', $rows[0]['ref']);

        Http::assertSent(function ($request): bool {
            if (! str_contains($request->url(), 'Document_In')) {
                return true;
            }

            return ! isset($request['$orderby']);
        });
    }

    public function test_create_counterparty_posts_to_odata(): void
    {
        config([
            'one_c.driver' => 'http',
            'one_c.base_url' => 'https://one-c.test/pub',
            'one_c.username' => 'Odata',
            'one_c.password' => 'x',
            'one_c.odata.counterparty_path' => '/odata/standard.odata/Catalog_Контрагенты',
        ]);

        Http::fake([
            'one-c.test/pub/odata/standard.odata/Catalog_*' => Http::response([
                'Ref_Key' => 'new-cp',
            ], 201),
        ]);

        $ref = app(OneCBpClient::class)->createCounterparty('7707083893', '770701001', 'Тест ООО');
        $this->assertSame('new-cp', $ref);
    }

    public function test_bridge_check_pending_only_does_not_escalate(): void
    {
        $assignee = User::factory()->create();
        config([
            'one_c.driver' => 'fake',
            'one_c.enabled' => true,
            'one_c.bridge.escalation_user_id' => $assignee->id,
            'one_c.publications' => [
                'autalliance' => [
                    'label' => 'АА',
                    'base_url' => 'https://one-c.test/aa',
                    'organization_ref' => 'org',
                    'bank_account_number' => '4070281095971000PEND',
                    'date_filter_mode' => 'odata',
                    'enabled' => true,
                ],
            ],
        ]);

        $account = ManagementBankAccount::query()->create([
            'bank_name' => 'Test',
            'account_number' => '4070281095971000PEND',
            'account_mask' => '****PEND',
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        ManagementStatementLine::query()->create([
            'import_id' => null,
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'bridge-pending-1'),
            'row_number' => 1,
            'operation_date' => '2026-08-01',
            'direction' => 'out',
            'amount' => 100,
            'currency' => 'RUB',
            'description' => 'pending test',
            'status' => 'pending',
            'source' => 'import',
        ]);

        $result = app(OneCBridgeCheckService::class)->check(null);
        $this->assertSame('ok', $result['status']);
        $this->assertNull($result['task_created']);
        $this->assertStringContainsString('Неразнесённых', $result['summary_ru']);
        $this->assertSame(0, Task::query()->count());
    }

    public function test_bridge_check_missing_bank_creates_deduped_escalation_task(): void
    {
        $assignee = User::factory()->create();
        config([
            'one_c.driver' => 'fake',
            'one_c.enabled' => true,
            'one_c.bridge.escalation_user_id' => $assignee->id,
            'one_c.publications' => [
                'autalliance' => [
                    'label' => 'АА',
                    'base_url' => 'https://one-c.test/aa',
                    'organization_ref' => 'org',
                    'bank_account_number' => '4070281095971000MISS',
                    'date_filter_mode' => 'odata',
                    'enabled' => true,
                ],
            ],
        ]);

        $first = app(OneCBridgeCheckService::class)->check(null);
        $this->assertSame('attention', $first['status']);
        $this->assertNotNull($first['task_created']);

        $second = app(OneCBridgeCheckService::class)->check(null);
        $this->assertNull($second['task_created']);

        $this->assertSame(1, Task::query()
            ->where('meta->'.OneCBridgeEscalationService::META_COMPANY, 'autalliance')
            ->count());
    }

    public function test_health_ok_when_no_pending(): void
    {
        config([
            'one_c.driver' => 'fake',
            'one_c.publications' => [
                'autalliance' => [
                    'label' => 'АА',
                    'base_url' => 'https://one-c.test/aa',
                    'organization_ref' => 'org',
                    'bank_account_number' => '4070281095971000OKOK',
                    'date_filter_mode' => 'odata',
                    'enabled' => true,
                ],
            ],
        ]);

        ManagementBankAccount::query()->create([
            'bank_name' => 'OK',
            'account_number' => '4070281095971000OKOK',
            'account_mask' => '****OKOK',
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $verdict = app(OneCBridgeHealthService::class)->evaluate();
        $this->assertSame('ok', $verdict['status']);
    }
}
