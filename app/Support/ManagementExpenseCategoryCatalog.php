<?php

namespace App\Support;

final class ManagementExpenseCategoryCatalog
{
    /**
     * @return list<array{code: string, name: string, kind: string, sort_order: int, include_in_budget?: bool}>
     */
    public static function systemCategories(): array
    {
        return [
            ['code' => 'operational_customer_in', 'name' => 'Оплата от заказчика', 'kind' => 'operational_in', 'sort_order' => 10],
            ['code' => 'operational_carrier_out', 'name' => 'Привлечённый транспорт', 'kind' => 'operational_out_hired', 'sort_order' => 20],
            ['code' => 'cost_own_fleet', 'name' => 'Собственный парк', 'kind' => 'operational_out_own_fleet', 'sort_order' => 25],
            ['code' => 'bank_fees', 'name' => 'Банковские комиссии и сборы', 'kind' => 'overhead', 'sort_order' => 30, 'include_in_budget' => true],
            ['code' => 'services_other', 'name' => 'Услуги и лицензии (прочее)', 'kind' => 'overhead', 'sort_order' => 40, 'include_in_budget' => true],
            ['code' => 'payroll_managers', 'name' => 'ФОТ менеджеры', 'kind' => 'payroll_other', 'sort_order' => 51, 'include_in_budget' => true],
            ['code' => 'payroll_office', 'name' => 'ФОТ бухгалтерия', 'kind' => 'payroll_other', 'sort_order' => 52, 'include_in_budget' => true],
            ['code' => 'cash_other_in', 'name' => 'Наличные / прочие поступления', 'kind' => 'cash', 'sort_order' => 80],
            ['code' => 'cash_other_out', 'name' => 'Наличные / прочие расходы', 'kind' => 'cash', 'sort_order' => 90],
            ['code' => 'unclassified', 'name' => 'Неразнесённое', 'kind' => 'unclassified', 'sort_order' => 100],
        ];
    }

    /**
     * @return list<string>
     */
    public static function legacyPayrollCodes(): array
    {
        return ['payroll_accrued_sales', 'payroll_paid_sales', 'payroll_other'];
    }
}
