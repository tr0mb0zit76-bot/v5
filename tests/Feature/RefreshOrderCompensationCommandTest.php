<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RefreshOrderCompensationCommandTest extends TestCase
{
    public function test_command_fails_for_missing_order(): void
    {
        if (! Schema::hasTable('orders')) {
            $this->markTestSkipped('Таблица orders недоступна в тестовой БД.');
        }

        $this->artisan('orders:refresh-compensation', ['order' => 2147483647])
            ->expectsOutput('Заказы не найдены.')
            ->assertFailed();
    }
}
