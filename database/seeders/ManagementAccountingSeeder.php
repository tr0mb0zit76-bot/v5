<?php

namespace Database\Seeders;

use App\Models\ManagementBankAccount;
use App\Models\ManagementExpenseCategory;
use Illuminate\Database\Seeder;

class ManagementAccountingSeeder extends Seeder
{
    public function run(): void
    {
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

        $categories = [
            ['code' => 'operational_customer_in', 'name' => 'Оплата от заказчика', 'kind' => 'operational_in', 'sort_order' => 10],
            ['code' => 'operational_carrier_out', 'name' => 'Оплата перевозчику', 'kind' => 'operational_out', 'sort_order' => 20],
            ['code' => 'bank_fees', 'name' => 'Банковские комиссии и сборы', 'kind' => 'overhead', 'sort_order' => 30],
            ['code' => 'services_other', 'name' => 'Услуги и лицензии (прочее)', 'kind' => 'overhead', 'sort_order' => 40],
            ['code' => 'payroll_accrued_sales', 'name' => 'ФОТ продавцы (начислено)', 'kind' => 'payroll_accrued', 'sort_order' => 50],
            ['code' => 'payroll_paid_sales', 'name' => 'ФОТ продавцы (выплачено)', 'kind' => 'payroll_paid', 'sort_order' => 60],
            ['code' => 'payroll_other', 'name' => 'ФОТ прочие', 'kind' => 'payroll_other', 'sort_order' => 70],
            ['code' => 'cash_other_in', 'name' => 'Наличные / прочие поступления', 'kind' => 'cash', 'sort_order' => 80],
            ['code' => 'cash_other_out', 'name' => 'Наличные / прочие расходы', 'kind' => 'cash', 'sort_order' => 90],
            ['code' => 'unclassified', 'name' => 'Неразнесённое', 'kind' => 'unclassified', 'sort_order' => 100],
        ];

        foreach ($categories as $category) {
            ManagementExpenseCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                [
                    ...$category,
                    'is_system' => true,
                    'is_active' => true,
                ],
            );
        }
    }
}
