<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Contractor;
use App\Models\User;
use App\Services\OrderWizardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClearCarrierTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_performer_contractor_updates_carrier_id()
    {
        // Создаем тестовые данные
        $user = User::factory()->create();
        $client = Contractor::factory()->create(['type' => 'customer']);
        $carrier = Contractor::factory()->create(['type' => 'carrier']);
        
        // Создаем заказ с перевозчиком
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'carrier_id' => $carrier->id,
            'performers' => json_encode([
                ['stage' => 'leg_1', 'contractor_id' => $carrier->id]
            ]),
        ]);

        // Имитируем отправку формы с очищенным перевозчиком
        $service = new OrderWizardService();
        
        $data = [
            'client_id' => $client->id,
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => null]
            ],
            'financial_term' => [
                'client_price' => 1000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat',
                'client_request_mode' => 'single_request',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 0,
                    'postpayment_mode' => 'fttn',
                ],
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => null,
                        'amount' => null,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 0,
                            'postpayment_mode' => 'fttn',
                        ]
                    ]
                ],
                'additional_costs' => [],
                'kpi_percent' => 0,
            ],
        ];

        // Обновляем заказ через сервис
        $updatedOrder = $service->updateOrder($order, $data, $user);

        // Проверяем, что carrier_id стал null
        $this->assertNull($updatedOrder->carrier_id);
        
        // Проверяем, что performers содержит null для contractor_id
        $performers = json_decode($updatedOrder->performers, true);
        $this->assertNull($performers[0]['contractor_id']);
        
        echo "Тест пройден: carrier_id успешно обновлен на null при очистке перевозчика\n";
    }

    public function test_clear_carrier_syncs_with_contractors_costs()
    {
        // Создаем тестовые данные
        $user = User::factory()->create();
        $client = Contractor::factory()->create(['type' => 'customer']);
        $carrier = Contractor::factory()->create(['type' => 'carrier']);
        
        // Создаем заказ с перевозчиком
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'carrier_id' => $carrier->id,
            'performers' => json_encode([
                ['stage' => 'leg_1', 'contractor_id' => $carrier->id]
            ]),
            'financial_term' => json_encode([
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => $carrier->id,
                        'amount' => 500,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 0,
                            'postpayment_mode' => 'fttn',
                        ]
                    ]
                ]
            ]),
        ]);

        // Имитируем отправку формы с очищенным перевозчиком
        $service = new OrderWizardService();
        
        $data = [
            'client_id' => $client->id,
            'performers' => [
                ['stage' => 'leg_1', 'contractor_id' => null]
            ],
            'financial_term' => [
                'client_price' => 1000,
                'client_currency' => 'RUB',
                'client_payment_form' => 'vat',
                'client_request_mode' => 'single_request',
                'client_payment_schedule' => [
                    'has_prepayment' => false,
                    'postpayment_days' => 0,
                    'postpayment_mode' => 'fttn',
                ],
                'contractors_costs' => [
                    [
                        'stage' => 'leg_1',
                        'contractor_id' => null,
                        'amount' => null,
                        'currency' => 'RUB',
                        'payment_form' => 'no_vat',
                        'payment_schedule' => [
                            'has_prepayment' => false,
                            'postpayment_days' => 0,
                            'postpayment_mode' => 'fttn',
                        ]
                    ]
                ],
                'additional_costs' => [],
                'kpi_percent' => 0,
            ],
        ];

        // Обновляем заказ через сервис
        $updatedOrder = $service->updateOrder($order, $data, $user);

        // Проверяем, что contractors_costs также обновлен
        $financialTerm = json_decode($updatedOrder->financial_term, true);
        $this->assertNull($financialTerm['contractors_costs'][0]['contractor_id']);
        
        echo "Тест пройден: contractors_costs успешно синхронизирован с очисткой перевозчика\n";
    }
}

// Запуск тестов
echo "=== Тестирование очистки перевозчика ===\n\n";

try {
    $test = new ClearCarrierTest();
    
    echo "1. Тест очистки перевозчика и обновления carrier_id:\n";
    $test->test_clear_performer_contractor_updates_carrier_id();
    
    echo "\n2. Тест синхронизации с contractors_costs:\n";
    $test->test_clear_carrier_syncs_with_contractors_costs();
    
    echo "\n=== Все тесты пройдены успешно ===\n";
} catch (\Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Стек вызовов:\n" . $e->getTraceAsString() . "\n";
}