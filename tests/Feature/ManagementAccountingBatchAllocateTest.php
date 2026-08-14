<?php

namespace Tests\Feature;

use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class ManagementAccountingBatchAllocateTest extends TestCase
{
    public function test_batch_allocate_allocates_ready_lines_of_import(): void
    {
        [$user, $import, $firstLine, $secondLine, $category] = $this->makeImportWithTwoPendingLines();

        $this->actingAs($user)
            ->from('/finance/management-accounting/imports/'.$import->id)
            ->post('/finance/management-accounting/imports/'.$import->id.'/allocate-batch', [
                'items' => [
                    [
                        'line_id' => $firstLine->id,
                        'allocation_type' => 'category',
                        'category_id' => $category->id,
                    ],
                    [
                        'line_id' => $secondLine->id,
                        'allocation_type' => 'category',
                        'category_id' => $category->id,
                    ],
                ],
            ])
            ->assertRedirect('/finance/management-accounting/imports/'.$import->id);

        $this->assertSame('allocated', $firstLine->fresh()->status);
        $this->assertSame('allocated', $secondLine->fresh()->status);
        $this->assertSame(2, (int) $import->fresh()->lines_allocated);
    }

    public function test_batch_allocate_rejects_lines_from_another_import(): void
    {
        [$user, $import, $firstLine, $secondLine, $category] = $this->makeImportWithTwoPendingLines();

        $foreignLine = ManagementStatementLine::query()->create([
            'import_id' => null,
            'bank_account_id' => $firstLine->bank_account_id,
            'line_hash' => hash('sha256', 'foreign-line'),
            'operation_date' => '2026-06-16',
            'direction' => 'out',
            'amount' => 200,
            'currency' => 'RUB',
            'description' => 'Foreign',
            'status' => 'pending',
            'source' => 'manual',
        ]);

        $this->actingAs($user)
            ->post('/finance/management-accounting/imports/'.$import->id.'/allocate-batch', [
                'items' => [
                    [
                        'line_id' => $firstLine->id,
                        'allocation_type' => 'category',
                        'category_id' => $category->id,
                    ],
                    [
                        'line_id' => $foreignLine->id,
                        'allocation_type' => 'category',
                        'category_id' => $category->id,
                    ],
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertSame('pending', $firstLine->fresh()->status);
        $this->assertSame('pending', $secondLine->fresh()->status);
        $this->assertSame('pending', $foreignLine->fresh()->status);
    }

    /**
     * @return array{0: User, 1: ManagementStatementImport, 2: ManagementStatementLine, 3: ManagementStatementLine, 4: ManagementExpenseCategory}
     */
    private function makeImportWithTwoPendingLines(): array
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'admin'],
            ['display_name' => 'Administrator'],
        );

        $user = User::factory()->create(['role_id' => $role->id]);

        $bankAccount = ManagementBankAccount::query()->create([
            'bank_name' => 'Сбер',
            'account_number' => '40702810123456789012',
            'account_mask' => '****9012',
            'currency' => 'RUB',
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bankAccount->id,
            'format' => 'bank_registry_v1',
            'file_name' => 'batch.xlsx',
            'period_from' => '2026-06-01',
            'period_to' => '2026-06-30',
            'imported_by' => $user->id,
            'status' => 'draft',
            'lines_count' => 2,
            'lines_allocated' => 0,
        ]);

        $category = $this->createManagementExpenseCategory([
            'code' => 'batch_cat_'.uniqid(),
            'name' => 'Комиссия банка',
        ]);

        $firstLine = ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $bankAccount->id,
            'line_hash' => hash('sha256', 'batch-1'),
            'operation_date' => '2026-06-15',
            'direction' => 'out',
            'amount' => 1000,
            'currency' => 'RUB',
            'description' => 'Комиссия 1',
            'status' => 'pending',
            'source' => 'import',
        ]);

        $secondLine = ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $bankAccount->id,
            'line_hash' => hash('sha256', 'batch-2'),
            'operation_date' => '2026-06-16',
            'direction' => 'out',
            'amount' => 500,
            'currency' => 'RUB',
            'description' => 'Комиссия 2',
            'status' => 'pending',
            'source' => 'import',
        ]);

        return [$user, $import, $firstLine, $secondLine, $category];
    }
}
