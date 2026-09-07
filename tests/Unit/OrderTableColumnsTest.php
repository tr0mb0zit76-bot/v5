<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\OrderTableColumns;
use Tests\TestCase;

class OrderTableColumnsTest extends TestCase
{
    public function test_salary_paid_column_has_readable_label(): void
    {
        $column = collect(OrderTableColumns::options())->firstWhere('field', 'salary_paid');

        $this->assertNotNull($column);
        $this->assertSame('Выплачено (зарплата)', $column['label']);
    }

    public function test_salary_paid_is_visible_by_default_for_manager_but_not_clerk(): void
    {
        $this->assertContains('salary_paid', OrderTableColumns::defaultVisibleFields('manager'));
        $this->assertContains('salary_paid', OrderTableColumns::defaultVisibleFields('admin'));
        $this->assertNotContains('salary_paid', OrderTableColumns::defaultVisibleFields('clerk'));
    }
}
