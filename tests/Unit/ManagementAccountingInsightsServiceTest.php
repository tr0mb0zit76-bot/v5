<?php

namespace Tests\Unit;

use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementImport;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingInsightsService;
use Tests\TestCase;

class ManagementAccountingInsightsServiceTest extends TestCase
{
    public function test_insights_returns_cfo_payload_for_management_accounting_user(): void
    {
        $user = User::factory()->create([
            'name' => 'Analyst',
            'email' => 'analyst@example.com',
            'can_management_accounting' => true,
        ]);

        $expense = ManagementExpenseCategory::query()
            ->where('code', 'bank_fees')
            ->firstOrFail();

        $bankAccount = $this->createManagementBankAccount();

        $orderId = $this->insertOrderRow([]);

        \DB::table('payment_schedule_payment_events')->insert([
            'order_id' => $orderId,
            'party' => 'customer',
            'amount' => 200000,
            'payment_date' => '2026-06-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->createManagementStatementLine([
            'import_id' => null,
            'bank_account_id' => $bankAccount->id,
            'line_hash' => 'out-1',
            'operation_date' => '2026-06-12',
            'direction' => 'out',
            'amount' => 50000,
            'status' => 'allocated',
            'allocation_category_id' => $expense->id,
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bankAccount->id,
            'file_name' => 'june.xlsx',
            'imported_by' => $user->id,
            'lines_count' => 2,
            'lines_allocated' => 1,
        ]);

        $this->createManagementStatementLine([
            'import_id' => $import->id,
            'bank_account_id' => $bankAccount->id,
            'line_hash' => 'pending-1',
            'operation_date' => '2026-06-15',
            'direction' => 'out',
            'amount' => 12000,
            'status' => 'pending',
        ]);

        $result = app(ManagementAccountingInsightsService::class)->insights($user, 'month', '2026-06-01');

        $this->assertTrue($result['available']);
        $this->assertSame('month', $result['period']['type']);
        $this->assertNotEmpty($result['executive_headline']);
        $this->assertSame(200000.0, $result['kpis']['revenue']);
        $this->assertGreaterThan(0, $result['reconciliation_health']['pending_lines']);
        $this->assertNotEmpty($result['recommendations']);
        $this->assertNotEmpty($result['risk_flags']);
        $this->assertNotEmpty($result['expense_mix']);
        $this->assertSame($expense->name, $result['expense_mix'][0]['name']);
    }

    public function test_insights_denied_without_management_accounting_access(): void
    {
        $user = User::factory()->create([
            'name' => 'User',
            'email' => 'user@example.com',
            'can_management_accounting' => false,
        ]);

        $result = app(ManagementAccountingInsightsService::class)->insights($user);

        $this->assertFalse($result['available']);
    }
}
