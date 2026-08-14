<?php

declare(strict_types=1);

namespace Tests\Unit\Services\ManagementAccounting;

use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingAutoAllocateService;
use App\Services\ManagementAccounting\ManagementAccountingStatementDuplicateService;
use App\Services\ManagementAccounting\ManagementAccountingStatementRewalkService;
use App\Support\ManagementStatementLineContentFingerprint;
use Tests\TestCase;

class ManagementAccountingStatementHygieneTest extends TestCase
{
    public function test_fingerprint_treats_xls_and_odata_same_invoice_as_duplicate(): void
    {
        $xls = 'АГРОПРОФ ООО / ТРАНСПОРТНО-ЭКСПЕДИЦИОННЫЕ УСЛУГИ ПО ЗАКАЗУ № 2 ОТ 28.07.2026. СУММА 285000-00 РУБ. В Т.Ч. НДС (22%) 51393-44 РУБ.';
        $odata = 'АГРОПРОФ ООО / ТРАНСПОРТНО-ЭКСПЕДИЦИОННЫЕ УСЛУГИ ПО ЗАКАЗУ № 2 ОТ 28.07.2026. Сумма 285000-00 В т.ч. НДС  (22%) 51393-44';

        $this->assertSame(
            ManagementStatementLineContentFingerprint::key('2026-08-04', 'in', 285000, $xls),
            ManagementStatementLineContentFingerprint::key('2026-08-04', 'in', 285000, $odata),
        );
    }

    public function test_fingerprint_keeps_same_client_same_amount_different_invoices_apart(): void
    {
        $first = 'ФЕДЕРАЛ РЕЗЕРВ ООО / Оплата за ТЭУ Счет № 74 от 29.07.2026 Сумма 80000-00';
        $second = 'ФЕДЕРАЛ РЕЗЕРВ ООО / Оплата за ТЭУ Счет № 76 от 04.08.2026 Сумма 80000-00';

        $this->assertNotSame(
            ManagementStatementLineContentFingerprint::key('2026-08-04', 'in', 80000, $first),
            ManagementStatementLineContentFingerprint::key('2026-08-04', 'in', 80000, $second),
        );
    }

    public function test_auto_allocate_does_not_dump_ambiguous_operational_onto_category(): void
    {
        $user = User::factory()->create();
        $account = ManagementBankAccount::query()->create([
            'bank_name' => 'Test',
            'account_number' => '40700000000000000001',
            'account_mask' => '****0001',
            'currency' => 'RUB',
        ]);
        $category = ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'operational_carrier_out'],
            ['name' => 'Привлечённый транспорт', 'kind' => 'expense', 'flow' => 'out', 'is_active' => true, 'is_system' => true, 'sort_order' => 1],
        );

        $line = ManagementStatementLine::query()->create([
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'ambig-'.uniqid()),
            'operation_date' => '2026-08-13',
            'direction' => 'out',
            'amount' => 35000,
            'currency' => 'RUB',
            'description' => 'АВТОСПЕЦСТРОЙ ООО / Оплата по счету 418',
            'status' => 'pending',
            'source' => 'one_c_odata',
            'match_type' => 'operational',
            'match_confidence' => 68,
            'match_notes' => 'Несколько заявок (Г-2607-0003, АС-ЗА-21): выберите строку графика',
            'suggested_category_id' => $category->id,
            'suggested_payment_schedule_id' => null,
        ]);

        $allocated = app(ManagementAccountingAutoAllocateService::class)->tryAutoAllocate($line, $user, 55);

        $this->assertFalse($allocated);
        $this->assertSame('pending', $line->fresh()->status);
    }

    public function test_auto_allocate_still_applies_true_category_keyword_match(): void
    {
        $user = User::factory()->create();
        $account = ManagementBankAccount::query()->create([
            'bank_name' => 'Test',
            'account_number' => '40700000000000000002',
            'account_mask' => '****0002',
            'currency' => 'RUB',
        ]);
        $category = ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'bank_fees'],
            ['name' => 'Комиссии банка', 'kind' => 'expense', 'flow' => 'out', 'is_active' => true, 'is_system' => true, 'sort_order' => 2],
        );

        $line = ManagementStatementLine::query()->create([
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'cat-'.uniqid()),
            'operation_date' => '2026-08-13',
            'direction' => 'out',
            'amount' => 40,
            'currency' => 'RUB',
            'description' => 'Комиссия банка',
            'status' => 'pending',
            'source' => 'one_c_odata',
            'match_type' => 'category',
            'match_confidence' => 80,
            'match_notes' => 'Ключевое слово: комисс',
            'suggested_category_id' => $category->id,
        ]);

        $allocated = app(ManagementAccountingAutoAllocateService::class)->tryAutoAllocate($line, $user, 55);

        $this->assertTrue($allocated);
        $this->assertSame('allocated', $line->fresh()->status);
        $this->assertSame($category->id, $line->fresh()->allocation_category_id);
    }

    public function test_pending_twin_of_allocated_line_is_deleted(): void
    {
        $account = ManagementBankAccount::query()->create([
            'bank_name' => 'Alfa',
            'account_number' => '40702810629940001726-test',
            'account_mask' => '****1726',
            'currency' => 'RUB',
        ]);

        $purpose = 'АГРОПРОФ ООО / ТРАНСПОРТНО-ЭКСПЕДИЦИОННЫЕ УСЛУГИ ПО ЗАКАЗУ № 2 ОТ 28.07.2026. СУММА 285000-00';

        $allocated = ManagementStatementLine::query()->create([
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'xls-agro'),
            'operation_date' => '2026-08-04',
            'direction' => 'in',
            'amount' => 285000,
            'currency' => 'RUB',
            'description' => $purpose,
            'status' => 'allocated',
            'source' => 'import',
            'match_type' => 'operational',
        ]);

        $pendingTwin = ManagementStatementLine::query()->create([
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'odata-agro'),
            'operation_date' => '2026-08-04',
            'direction' => 'in',
            'amount' => 285000,
            'currency' => 'RUB',
            'description' => $purpose.' В Т.Ч. НДС (22%) 51393-44 РУБ.',
            'status' => 'pending',
            'source' => 'one_c_odata',
        ]);

        $otherSameAmount = ManagementStatementLine::query()->create([
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'other-invoice'),
            'operation_date' => '2026-08-04',
            'direction' => 'in',
            'amount' => 285000,
            'currency' => 'RUB',
            'description' => 'АГРОПРОФ ООО / ТРАНСПОРТНО-ЭКСПЕДИЦИОННЫЕ УСЛУГИ ПО ЗАКАЗУ № 9 ОТ 01.08.2026. СУММА 285000-00',
            'status' => 'pending',
            'source' => 'one_c_odata',
        ]);

        $deleted = app(ManagementAccountingStatementDuplicateService::class)->deletePendingTwins();

        $this->assertContains($pendingTwin->id, $deleted);
        $this->assertNull(ManagementStatementLine::query()->find($pendingTwin->id));
        $this->assertNotNull(ManagementStatementLine::query()->find($allocated->id));
        $this->assertNotNull(ManagementStatementLine::query()->find($otherSameAmount->id));
    }

    public function test_rewalk_releases_ambiguous_category_dump_and_keeps_other_invoice(): void
    {
        $user = User::factory()->create(['can_management_accounting' => true]);
        $account = ManagementBankAccount::query()->create([
            'bank_name' => 'Alfa',
            'account_number' => '40702810629940001726-rewalk',
            'account_mask' => '****1726',
            'currency' => 'RUB',
        ]);
        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $account->id,
            'format' => 'one_c_odata_v1',
            'file_name' => 'rewalk-test',
            'imported_by' => $user->id,
            'status' => 'draft',
            'lines_count' => 3,
            'lines_allocated' => 1,
        ]);
        $category = ManagementExpenseCategory::query()->firstOrCreate(
            ['code' => 'operational_carrier_out'],
            ['name' => 'Привлечённый транспорт', 'kind' => 'expense', 'flow' => 'out', 'is_active' => true, 'is_system' => true, 'sort_order' => 1],
        );

        $dump = ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'dump-autospec'),
            'operation_date' => '2026-08-13',
            'direction' => 'out',
            'amount' => 35000,
            'currency' => 'RUB',
            'description' => 'АВТОСПЕЦСТРОЙ ООО / Оплата по счету 418',
            'status' => 'allocated',
            'source' => 'one_c_odata',
            'match_type' => 'category',
            'match_confidence' => 68,
            'match_notes' => 'Несколько заявок (Г-2607-0003, АС-ЗА-21): выберите строку графика',
            'suggested_category_id' => $category->id,
            'allocation_category_id' => $category->id,
            'allocated_by' => $user->id,
            'allocated_at' => now(),
        ]);

        $allocatedXls = ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'xls-keep'),
            'operation_date' => '2026-08-04',
            'direction' => 'in',
            'amount' => 80000,
            'currency' => 'RUB',
            'description' => 'ФЕДЕРАЛ РЕЗЕРВ ООО / Оплата за ТЭУ Счет № 74 от 29.07.2026 Сумма 80000-00',
            'status' => 'allocated',
            'source' => 'import',
            'match_type' => 'operational',
            'allocated_by' => $user->id,
            'allocated_at' => now(),
        ]);

        $pendingOtherInvoice = ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'odata-other-invoice'),
            'operation_date' => '2026-08-04',
            'direction' => 'in',
            'amount' => 80000,
            'currency' => 'RUB',
            'description' => 'ФЕДЕРАЛ РЕЗЕРВ ООО / Оплата за ТЭУ Счет № 76 от 04.08.2026 Сумма 80000-00',
            'status' => 'pending',
            'source' => 'one_c_odata',
        ]);

        $pendingTwin = ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $account->id,
            'line_hash' => hash('sha256', 'odata-twin-74'),
            'operation_date' => '2026-08-04',
            'direction' => 'in',
            'amount' => 80000,
            'currency' => 'RUB',
            'description' => 'ФЕДЕРАЛ РЕЗЕРВ ООО / Оплата за ТЭУ Счет № 74 от 29.07.2026. Сумма 80000-00 В т.ч. НДС',
            'status' => 'pending',
            'source' => 'one_c_odata',
        ]);

        $stats = app(ManagementAccountingStatementRewalkService::class)->rewalk($user, 55);

        $this->assertSame(1, $stats['duplicates_deleted']);
        $this->assertSame(1, $stats['ambiguous_released']);
        $this->assertNull(ManagementStatementLine::query()->find($pendingTwin->id));
        $this->assertNotNull(ManagementStatementLine::query()->find($pendingOtherInvoice->id));
        $this->assertNotNull(ManagementStatementLine::query()->find($allocatedXls->id));
        $this->assertSame('pending', $dump->fresh()->status);
        $this->assertNull($dump->fresh()->allocation_category_id);
    }
}
