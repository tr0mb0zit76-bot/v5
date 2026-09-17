<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\ManagementStatementImport;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\ManagementAccounting\CashFlowExecutiveBriefService;
use App\Services\ManagementAccounting\ManagementAccountingInsightsService;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CashFlowExecutiveBriefServiceTest extends TestCase
{
    public function test_brief_denied_without_management_accounting_access(): void
    {
        $user = User::factory()->create([
            'can_management_accounting' => false,
        ]);

        $result = app(CashFlowExecutiveBriefService::class)->brief($user, '2026-06-01', '2026-06-30');

        $this->assertFalse($result['available']);
    }

    public function test_brief_builds_cash_flow_ar_ap_and_without_order(): void
    {
        $user = User::factory()->create([
            'can_management_accounting' => true,
        ]);

        $bank = $this->createManagementBankAccount();

        $this->createManagementStatementLine([
            'bank_account_id' => $bank->id,
            'line_hash' => hash('sha256', 'in-1'),
            'operation_date' => '2026-06-05',
            'direction' => 'in',
            'amount' => 500000,
            'description' => 'ФАРМСЕРВИС ООО / Оплата по счету',
            'status' => 'allocated',
            'allocation_order_id' => null,
        ]);

        $this->createManagementStatementLine([
            'bank_account_id' => $bank->id,
            'line_hash' => hash('sha256', 'out-carrier'),
            'operation_date' => '2026-06-10',
            'direction' => 'out',
            'amount' => 200000,
            'description' => 'М-МОТОРС ООО / Транспортные услуги',
            'status' => 'allocated',
            'allocation_order_id' => null,
        ]);

        $this->createManagementStatementLine([
            'bank_account_id' => $bank->id,
            'line_hash' => hash('sha256', 'out-lease'),
            'operation_date' => '2026-06-12',
            'direction' => 'out',
            'amount' => 100000,
            'description' => 'РЕСО-АВТОЛИЗИНГ ООО / Оплата по счету',
            'status' => 'allocated',
            'allocation_order_id' => null,
        ]);

        $this->createManagementStatementLine([
            'bank_account_id' => $bank->id,
            'line_hash' => hash('sha256', 'pending-out'),
            'operation_date' => '2026-06-20',
            'direction' => 'out',
            'amount' => 50000,
            'description' => 'ИМПЕРИЯ ООО / Транспорт',
            'status' => 'pending',
            'allocation_order_id' => null,
        ]);

        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'Артистрой',
        ]);
        $carrier = Contractor::query()->create([
            'type' => 'carrier',
            'name' => 'Сульда',
        ]);

        $orderId = $this->insertOrderRow([
            'order_number' => 'АС-TEST-001',
            'created_at' => '2026-06-01 10:00:00',
            'updated_at' => '2026-06-01 10:00:00',
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $orderId,
            'party' => 'customer',
            'type' => 'final',
            'amount' => 170000,
            'remaining_amount' => 170000,
            'status' => 'pending',
            'counterparty_id' => $customer->id,
        ]);

        PaymentSchedule::query()->create([
            'order_id' => $orderId,
            'party' => 'carrier',
            'type' => 'final',
            'amount' => 250000,
            'remaining_amount' => 250000,
            'status' => 'pending',
            'counterparty_id' => $carrier->id,
        ]);

        $result = app(CashFlowExecutiveBriefService::class)->brief($user, '2026-06-01', '2026-06-30');

        $this->assertTrue($result['available']);
        $this->assertSame(500000.0, $result['cash_flow']['in']);
        $this->assertSame(350000.0, $result['cash_flow']['out']);
        $this->assertSame(150000.0, $result['cash_flow']['net']);
        $this->assertSame(50000.0, $result['pending']['out_amount']);
        $this->assertGreaterThan(0, $result['outflows_without_crm_order']['total_amount']);
        $this->assertSame(170000.0, $result['accounts_receivable']['total']);
        $this->assertSame(250000.0, $result['accounts_payable']['total']);
        $this->assertNotEmpty($result['executive_headline']);
        $this->assertNotEmpty($result['diagnosis']);
        $this->assertNotEmpty($result['recommended_actions']);
        $this->assertNotEmpty($result['outflow_structure']);
        $this->assertSame('ФАРМСЕРВИС ООО', $result['top_in_counterparties'][0]['name']);
    }

    public function test_insights_pending_visible_for_non_admin_ma_user_on_other_imports(): void
    {
        if (! Schema::hasTable('management_statement_imports')) {
            $this->markTestSkipped('imports table missing');
        }

        $boss = User::factory()->create([
            'can_management_accounting' => true,
        ]);
        $accountant = User::factory()->create([
            'can_management_accounting' => true,
        ]);

        $bank = $this->createManagementBankAccount();
        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bank->id,
            'file_name' => 'acc.xlsx',
            'imported_by' => $accountant->id,
            'lines_count' => 1,
            'lines_allocated' => 0,
        ]);

        $this->createManagementStatementLine([
            'import_id' => $import->id,
            'bank_account_id' => $bank->id,
            'line_hash' => hash('sha256', 'boss-sees-pending'),
            'operation_date' => now()->toDateString(),
            'direction' => 'out',
            'amount' => 7777,
            'status' => 'pending',
        ]);

        $result = app(ManagementAccountingInsightsService::class)
            ->insights($boss, 'month', now()->toDateString());

        $this->assertTrue($result['available']);
        $this->assertGreaterThanOrEqual(1, $result['reconciliation_health']['pending_lines']);
        $this->assertGreaterThanOrEqual(7777.0, $result['reconciliation_health']['pending_amount']);
    }
}
