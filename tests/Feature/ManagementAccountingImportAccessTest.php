<?php

namespace Tests\Feature;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class ManagementAccountingImportAccessTest extends TestCase
{
    public function test_user_with_reconcile_access_can_open_import_uploaded_by_someone_else(): void
    {
        $role = Role::query()->create([
            'name' => 'accountant',
            'display_name' => 'Accountant',
            'visibility_areas' => ['finance_payment_reconcile'],
        ]);

        $owner = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
        ]);

        $editor = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Editor',
            'email' => 'editor@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
        ]);

        $bankAccount = ManagementBankAccount::query()->create([
            'bank_name' => 'Сбер',
            'account_number' => '40702810123456789012',
            'account_mask' => '****9012',
            'currency' => 'RUB',
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bankAccount->id,
            'format' => 'bank_registry_v1',
            'file_name' => 'statement.xlsx',
            'period_from' => '2026-06-01',
            'period_to' => '2026-06-30',
            'imported_by' => $owner->id,
            'status' => 'draft',
            'lines_count' => 2,
            'lines_allocated' => 2,
        ]);

        $this->actingAs($editor)
            ->get('/finance/management-accounting/imports/'.$import->id.'?filter=allocated')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/ManagementAccounting/Reconcile')
                ->where('filters.line_filter', 'allocated'));
    }

    public function test_user_with_reconcile_access_can_delete_import(): void
    {
        $role = Role::query()->create([
            'name' => 'accountant',
            'display_name' => 'Accountant',
            'visibility_areas' => ['finance_payment_reconcile'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Accountant',
            'email' => 'accountant@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
        ]);

        $bankAccount = ManagementBankAccount::query()->create([
            'bank_name' => 'Сбер',
            'account_number' => '40702810987654321098',
            'account_mask' => '****1098',
            'currency' => 'RUB',
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bankAccount->id,
            'format' => 'bank_registry_v1',
            'file_name' => 'duplicate.xlsx',
            'period_from' => '2026-06-01',
            'period_to' => '2026-06-30',
            'imported_by' => $user->id,
            'status' => 'draft',
            'lines_count' => 1,
            'lines_allocated' => 0,
        ]);

        ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $bankAccount->id,
            'line_hash' => hash('sha256', 'test-line'),
            'operation_date' => '2026-06-15',
            'direction' => 'out',
            'amount' => 1000,
            'currency' => 'RUB',
            'description' => 'Test payment',
            'status' => 'pending',
            'source' => 'import',
        ]);

        $this->actingAs($user)
            ->delete('/finance/management-accounting/imports/'.$import->id)
            ->assertRedirect('/finance?section=cashflow&cashflow_tab=reconcile');

        $this->assertDatabaseMissing('management_statement_imports', ['id' => $import->id]);
        $this->assertDatabaseMissing('management_statement_lines', ['import_id' => $import->id]);
    }

    public function test_show_reconcile_page_with_pending_lines_does_not_query_missing_order_columns(): void
    {
        $role = Role::query()->create([
            'name' => 'accountant',
            'display_name' => 'Accountant',
            'visibility_areas' => ['finance_payment_reconcile'],
        ]);

        $user = User::query()->create([
            'role_id' => $role->id,
            'name' => 'Accountant',
            'email' => 'show-lines@example.com',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'can_management_accounting' => true,
        ]);

        $bankAccount = ManagementBankAccount::query()->create([
            'bank_name' => 'Сбер',
            'account_number' => '40702810111111111111',
            'account_mask' => '****1111',
            'currency' => 'RUB',
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bankAccount->id,
            'format' => 'bank_registry_v1',
            'file_name' => 'АС 10.06-14.06.xlsx',
            'period_from' => '2026-06-10',
            'period_to' => '2026-06-14',
            'imported_by' => $user->id,
            'status' => 'draft',
            'lines_count' => 1,
            'lines_allocated' => 0,
        ]);

        ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $bankAccount->id,
            'line_hash' => hash('sha256', 'tandem-line'),
            'operation_date' => '2026-06-11',
            'direction' => 'out',
            'amount' => 78000,
            'currency' => 'RUB',
            'description' => 'ТК ТАНДЕМ ООО / счет 321 от 10.06.2026',
            'status' => 'pending',
            'source' => 'import',
        ]);

        $this->actingAs($user)
            ->get('/finance/management-accounting/imports/'.$import->id.'?filter=pending')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Finance/ManagementAccounting/Reconcile')
                ->has('lines', 1));
    }
}
