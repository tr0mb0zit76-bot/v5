<?php

namespace Tests\Feature\Orders;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CarrierSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);
    }

    /** @test */
    public function it_saves_carrier_changes_correctly()
    {
        // Создаем двух исполнителей
        $carrier1 = Contractor::factory()->create(['type' => 'carrier']);
        $carrier2 = Contractor::factory()->create(['type' => 'carrier']);

        // Создаем заказ с первым исполнителем
        $order = Order::factory()->create([
            'carrier_id' => $carrier1->id,
            'performers' => json_encode([[
                'stage' => 'main',
                'contractor_id' => $carrier1->id,
            ]]),
        ]);

        // Обновляем заказ, заменяя исполнителя
        $response = $this->patch(route('orders.update', $order->id), [
            'performers' => [[
                'stage' => 'main',
                'contractor_id' => $carrier2->id,
            ]],
            'financial_term' => [
                'contractors_costs' => [[
                    'stage' => 'main',
                    'contractor_id' => $carrier2->id,
                    'amount' => 1000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [],
                ]],
            ],
        ]);

        $response->assertRedirect();

        // Проверяем, что данные обновились в базе
        $order->refresh();

        $this->assertEquals($carrier2->id, $order->carrier_id);
        $this->assertEquals([[
            'stage' => 'main',
            'contractor_id' => $carrier2->id,
        ]], json_decode($order->performers, true));
    }

    /** @test */
    public function it_removes_carrier_when_performers_is_empty()
    {
        // Создаем исполнителя
        $carrier = Contractor::factory()->create(['type' => 'carrier']);

        // Создаем заказ с исполнителем
        $order = Order::factory()->create([
            'carrier_id' => $carrier->id,
            'performers' => json_encode([[
                'stage' => 'main',
                'contractor_id' => $carrier->id,
            ]]),
        ]);

        // Обновляем заказ, удаляя исполнителя
        $response = $this->patch(route('orders.update', $order->id), [
            'performers' => [],
            'financial_term' => [
                'contractors_costs' => [],
            ],
        ]);

        $response->assertRedirect();

        // Проверяем, что данные обновились в базе
        $order->refresh();

        $this->assertNull($order->carrier_id);
        $this->assertEquals([], json_decode($order->performers, true));
    }

    /** @test */
    public function it_persists_carrier_changes_after_page_reload()
    {
        // Создаем двух исполнителей
        $carrier1 = Contractor::factory()->create(['type' => 'carrier']);
        $carrier2 = Contractor::factory()->create(['type' => 'carrier']);

        // Создаем заказ с первым исполнителем
        $order = Order::factory()->create([
            'carrier_id' => $carrier1->id,
            'performers' => json_encode([[
                'stage' => 'main',
                'contractor_id' => $carrier1->id,
            ]]),
        ]);

        // Обновляем заказ, заменяя исполнителя
        $this->patch(route('orders.update', $order->id), [
            'performers' => [[
                'stage' => 'main',
                'contractor_id' => $carrier2->id,
            ]],
            'financial_term' => [
                'contractors_costs' => [[
                    'stage' => 'main',
                    'contractor_id' => $carrier2->id,
                    'amount' => 1000,
                    'currency' => 'RUB',
                    'payment_form' => 'no_vat',
                    'payment_schedule' => [],
                ]],
            ],
        ]);

        // "Перезагружаем" страницу - получаем данные заказа через API
        $response = $this->get(route('orders.edit', $order->id));

        // Проверяем, что данные сохранились после "перезагрузки"
        $order->refresh();

        $this->assertEquals($carrier2->id, $order->carrier_id);
        $this->assertEquals([[
            'stage' => 'main',
            'contractor_id' => $carrier2->id,
        ]], json_decode($order->performers, true));
    }
}
