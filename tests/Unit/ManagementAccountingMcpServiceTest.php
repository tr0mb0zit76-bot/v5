<?php

namespace Tests\Unit;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\Role;
use App\Models\User;
use App\Services\Mcp\ManagementAccountingMcpService;
use Illuminate\Auth\AuthenticationException;
use Tests\TestCase;

class ManagementAccountingMcpServiceTest extends TestCase
{
    public function test_list_lines_allows_importer_and_denies_other_user(): void
    {
        $owner = $this->makeUser(true, 'owner-svc@example.com');
        $other = $this->makeUser(true, 'other-svc@example.com');

        $bank = ManagementBankAccount::query()->create([
            'bank_name' => 'Сбер',
            'account_number' => '40702810123456789012',
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $import = ManagementStatementImport::query()->create([
            'bank_account_id' => $bank->id,
            'format' => 'sber_registry_v1',
            'file_name' => 'svc.xlsx',
            'imported_by' => $owner->id,
            'status' => 'ready',
            'lines_count' => 1,
        ]);

        ManagementStatementLine::query()->create([
            'import_id' => $import->id,
            'bank_account_id' => $bank->id,
            'line_hash' => 'svc-line',
            'operation_date' => '2026-06-02',
            'direction' => 'out',
            'amount' => 100,
            'description' => 'Тестовая строка',
            'status' => 'pending',
        ]);

        $service = app(ManagementAccountingMcpService::class);

        $lines = $service->listLines($owner, $import->id);
        $this->assertCount(1, $lines);
        $this->assertSame('Тестовая строка', $lines[0]['description']);

        $this->expectException(AuthenticationException::class);
        $service->listLines($other, $import->id);
    }

    private function makeUser(bool $canManagement, string $email): User
    {
        $role = Role::query()->create([
            'name' => 'svc_'.uniqid(),
            'display_name' => 'Svc',
            'visibility_areas' => ['documents'],
        ]);

        return User::query()->create([
            'role_id' => $role->id,
            'name' => 'User',
            'email' => $email,
            'password' => bcrypt('password'),
            'can_management_accounting' => $canManagement,
            'is_active' => true,
        ]);
    }
}
