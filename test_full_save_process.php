<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;

echo "Тестирование полного процесса сохранения\n\n";

// Найдем существующий заказ для теста
$order = Order::first();
if (!$order) {
    echo "Нет заказов в базе. Создаем тестовый заказ...\n";
    
    // Создаем тестовый заказ
    $order = Order::create([
        'order_number' => 'TEST-001',
        'company_code' => 'TEST',
        'manager_id' => 1,
        'status' => 'new',
        'customer_id' => 186,
        'carrier_id' => 2053, // Старый исполнитель
        'performers' => json_encode([['stage' => 'Плечо 1', 'contractor_id' => 2053]]),
    ]);
}

echo "Текущий заказ ID: " . $order->id . "\n";
echo "Текущий carrier_id: " . ($order->carrier_id ?? 'null') . "\n";
echo "Текущий performers: " . json_encode($order->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n\n";

// Данные для обновления (новый исполнитель 970)
$validated = [
    'status' => 'new',
    'own_company_id' => 2367,
    'client_id' => 186,
    'order_date' => '2026-04-07',
    'order_number' => $order->order_number,
    'payment_terms' => '{"client":{"payment_form":"vat","request_mode":"single_request","payment_schedule":{"has_prepayment":"0","prepayment_ratio":"50","prepayment_days":"0","prepayment_mode":"fttn","postpayment_days":"5","postpayment_mode":"ottn"}},"carriers":[{"stage":"Плечо 1","contractor_id":970,"payment_form":"no_vat","payment_schedule":{"has_prepayment":"0","prepayment_ratio":"50","prepayment_days":"0","prepayment_mode":"fttn","postpayment_days":"0","postpayment_mode":"ottn"}}]}',
    'special_notes' => null,
    'performers' => [
        ['stage' => 'Плечо 1', 'contractor_id' => 970] // Новый исполнитель
    ],
    'route_points' => [],
    'cargo_items' => [],
    'documents' => [],
    'financial_term' => [
        'client_price' => '100000.00',
        'client_currency' => 'RUB',
        'client_payment_form' => 'vat',
        'client_request_mode' => 'single_request',
        'client_payment_schedule' => [
            'has_prepayment' => '0',
            'prepayment_ratio' => '50',
            'prepayment_days' => '0',
            'prepayment_mode' => 'fttn',
            'postpayment_days' => '5',
            'postpayment_mode' => 'ottn'
        ],
        'contractors_costs' => [
            [
                'stage' => 'Плечо 1',
                'contractor_id' => 970,
                'amount' => null,
                'currency' => 'RUB',
                'payment_form' => 'no_vat',
                'payment_schedule' => [
                    'has_prepayment' => '0',
                    'prepayment_ratio' => '50',
                    'prepayment_days' => '0',
                    'prepayment_mode' => 'fttn',
                    'postpayment_days' => '0',
                    'postpayment_mode' => 'ottn'
                ]
            ]
        ],
        'additional_costs' => [],
        'kpi_percent' => 0
    ]
];

echo "Данные для обновления:\n";
echo "performers[0].contractor_id = " . $validated['performers'][0]['contractor_id'] . "\n";
echo "financial_term.contractors_costs[0].contractor_id = " . $validated['financial_term']['contractors_costs'][0]['contractor_id'] . "\n\n";

// Получаем пользователя
$user = User::find(1);
if (!$user) {
    $user = new User();
    $user->id = 1;
    $user->name = 'Test User';
}

// Вызываем метод update
$service = app(OrderWizardService::class);

try {
    echo "Вызываем OrderWizardService::update()...\n";
    $updatedOrder = $service->update($order, $validated, $user);
    
    echo "\nРезультат обновления:\n";
    echo "ID заказа: " . $updatedOrder->id . "\n";
    echo "carrier_id: " . ($updatedOrder->carrier_id ?? 'null') . "\n";
    echo "performers: " . json_encode($updatedOrder->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Проверяем financial_terms
    if (method_exists($updatedOrder, 'financialTerms')) {
        $financialTerm = $updatedOrder->financialTerms->first();
        if ($financialTerm) {
            echo "financial_terms.contractors_costs: " . json_encode($financialTerm->contractors_costs ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
    echo "\nПроверка:\n";
    if ($updatedOrder->carrier_id == 970) {
        echo "✓ carrier_id успешно обновлен на 970\n";
    } else {
        echo "✗ carrier_id не обновлен. Ожидалось: 970, получено: " . ($updatedOrder->carrier_id ?? 'null') . "\n";
    }
    
    $performers = $updatedOrder->performers ?? [];
    if (!empty($performers) && ($performers[0]['contractor_id'] ?? null) == 970) {
        echo "✓ performers[0].contractor_id успешно обновлен на 970\n";
    } else {
        echo "✗ performers[0].contractor_id не обновлен\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка при обновлении: " . $e->getMessage() . "\n";
    echo "Трассировка: " . $e->getTraceAsString() . "\n";
}

echo "\nПроверка завершена.\n";