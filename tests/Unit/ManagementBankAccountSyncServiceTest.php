<?php

namespace Tests\Unit;

use App\Models\Contractor;
use App\Models\ManagementBankAccount;
use App\Services\ManagementAccounting\ManagementBankAccountSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ManagementBankAccountSyncServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['management_bank_accounts', 'contractors']);

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_own_company')->default(false);
            $table->json('bank_accounts')->nullable();
            $table->timestamps();
        });

        Schema::create('management_bank_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('bank_name');
            $table->string('account_number', 32)->unique();
            $table->string('account_mask', 16)->nullable();
            $table->string('currency', 3)->default('RUB');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function test_syncs_primary_bank_account_from_own_company(): void
    {
        Contractor::query()->create([
            'name' => 'ООО Автоальянс',
            'is_active' => true,
            'is_own_company' => true,
            'bank_accounts' => [
                [
                    'id' => 'bank-secondary',
                    'bank_name' => 'ВТБ',
                    'account_number' => '40702810100000000002',
                    'currency' => 'RUB',
                    'is_primary' => false,
                ],
                [
                    'id' => 'bank-primary',
                    'bank_name' => 'Сбербанк',
                    'account_number' => '40702810959710001997',
                    'currency' => 'RUB',
                    'is_primary' => true,
                ],
            ],
        ]);

        app(ManagementBankAccountSyncService::class)->syncFromOwnCompanies();

        $accounts = ManagementBankAccount::query()->orderBy('sort_order')->get();

        $this->assertCount(2, $accounts);
        $this->assertSame('40702810959710001997', $accounts->first()?->account_number);
        $this->assertSame('Сбербанк', $accounts->first()?->bank_name);
        $this->assertSame('****1997', $accounts->first()?->account_mask);
    }

    public function test_ignores_non_own_company_accounts(): void
    {
        Contractor::query()->create([
            'name' => 'ООО Клиент',
            'is_active' => true,
            'is_own_company' => false,
            'bank_accounts' => [
                [
                    'bank_name' => 'Сбербанк',
                    'account_number' => '40702810959710001997',
                    'currency' => 'RUB',
                    'is_primary' => true,
                ],
            ],
        ]);

        app(ManagementBankAccountSyncService::class)->syncFromOwnCompanies();

        $this->assertSame(0, ManagementBankAccount::query()->count());
    }
}
