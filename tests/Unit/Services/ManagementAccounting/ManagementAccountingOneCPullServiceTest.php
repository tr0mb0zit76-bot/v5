<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ManagementAccounting;

use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementLine;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingOneCPullService;
use App\Services\OneC\OneCBpClient;
use Tests\TestCase;

class ManagementAccountingOneCPullServiceTest extends TestCase
{
    public function test_client_fake_returns_two_movements(): void
    {
        config(['one_c.driver' => 'fake']);
        $rows = app(OneCBpClient::class)->listBankMovements('2026-07-31', '2026-08-01');
        $this->assertCount(2, $rows);
        $this->assertSame('in', $rows[0]['direction']);
        $this->assertSame('out', $rows[1]['direction']);
    }

    public function test_pull_creates_lines_and_skips_duplicates(): void
    {
        config([
            'one_c.driver' => 'fake',
            'one_c.enabled' => true,
            'one_c.bank_statement.account_number' => '40702810959710001997-test-pull',
        ]);

        $account = ManagementBankAccount::query()->create([
            'bank_name' => 'Sber test pull',
            'account_number' => '40702810959710001997-test-pull',
            'account_mask' => '****pull',
            'currency' => 'RUB',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'bank_fees'],
            [
                'name' => 'Комиссии банка',
                'kind' => 'expense',
                'flow' => 'out',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 10,
            ],
        );

        ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'operational_customer_in'],
            [
                'name' => 'Оплаты заказчиков',
                'kind' => 'income',
                'flow' => 'in',
                'is_active' => true,
                'is_system' => true,
                'sort_order' => 20,
            ],
        );

        $user = User::factory()->create();

        $result = app(ManagementAccountingOneCPullService::class)->pullAndImport(
            '2026-07-31',
            '2026-08-01',
            $user,
            allocate: true,
            minConfidence: 40,
            bankAccount: $account,
        );

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, $result['fetched']);
        $this->assertGreaterThanOrEqual(1, $result['allocated']);

        $lines = ManagementStatementLine::query()->where('import_id', $result['import']->id)->get();
        $this->assertCount(2, $lines);
        $this->assertTrue($lines->contains(fn ($l) => str_contains((string) $l->description, 'ТЕСТ КОНТРАГЕНТ')));
        $this->assertSame('one_c_odata', $lines->first()->source);

        $this->expectException(\InvalidArgumentException::class);
        app(ManagementAccountingOneCPullService::class)->pullAndImport(
            '2026-07-31',
            '2026-08-01',
            $user,
            allocate: false,
            bankAccount: $account,
        );
    }
}
