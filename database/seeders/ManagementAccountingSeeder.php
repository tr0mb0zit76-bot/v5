<?php

namespace Database\Seeders;

use App\Models\ManagementBankAccount;
use App\Services\ManagementAccounting\ManagementExpenseCategorySyncService;
use Illuminate\Database\Seeder;

class ManagementAccountingSeeder extends Seeder
{
    public function run(): void
    {
        ManagementBankAccount::consolidated();

        $accounts = [
            [
                'bank_name' => 'Сбербанк',
                'account_number' => '40702810959710001997',
                'account_mask' => '****1997',
                'currency' => 'RUB',
                'sort_order' => 10,
            ],
            [
                'bank_name' => 'Банк 2 (рубли)',
                'account_number' => '40702810900000000001',
                'account_mask' => '****0001',
                'currency' => 'RUB',
                'sort_order' => 20,
            ],
            [
                'bank_name' => 'Банк 2 (юани)',
                'account_number' => '40702810900000000002',
                'account_mask' => '****0002',
                'currency' => 'CNY',
                'sort_order' => 30,
            ],
        ];

        foreach ($accounts as $account) {
            ManagementBankAccount::query()->updateOrCreate(
                ['account_number' => $account['account_number']],
                $account,
            );
        }

        app(ManagementExpenseCategorySyncService::class)->syncAll();
    }
}
