<?php

namespace Tests\Unit;

use App\Models\ManagementBankAccount;
use App\Models\ManagementStatementImport;
use App\Models\ManagementStatementLine;
use App\Models\Role;
use App\Models\User;
use App\Services\Mcp\ManagementAccountingMcpService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementAccountingMcpServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'management_statement_lines',
            'management_statement_imports',
            'management_bank_accounts',
            'payment_schedules',
            'orders',
            'role_user',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('permissions')->nullable();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->boolean('can_management_accounting')->default(false);
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id');
            $table->foreignId('user_id');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_schedules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('party', 16)->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('management_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('bank_name');
            $table->string('account_number', 32)->unique();
            $table->string('currency', 3)->default('RUB');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('management_statement_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('bank_account_id');
            $table->string('format', 32);
            $table->string('file_name');
            $table->foreignId('imported_by');
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('lines_count')->default(0);
            $table->unsignedInteger('lines_allocated')->default(0);
            $table->decimal('total_in', 14, 2)->default(0);
            $table->decimal('total_out', 14, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('management_statement_lines', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('import_id')->nullable();
            $table->foreignId('bank_account_id');
            $table->string('line_hash', 64);
            $table->unsignedInteger('row_number')->nullable();
            $table->date('operation_date');
            $table->string('direction', 8);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('RUB');
            $table->text('description');
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('suggested_order_id')->nullable();
            $table->unsignedBigInteger('suggested_payment_schedule_id')->nullable();
            $table->unsignedBigInteger('suggested_category_id')->nullable();
            $table->unsignedBigInteger('suggested_user_id')->nullable();
            $table->timestamps();
        });
    }

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
