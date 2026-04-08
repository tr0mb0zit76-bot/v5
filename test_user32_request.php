<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;

echo "Тестирование обновления заказа от пользователя ID: 32\n\n";

// Найдем пользователя
$user = User::find(32);
if (!$user) {
    echo "Пользователь с ID 32 не найден\n";
    exit;
}

echo "Пользователь: " . $user->name . " (ID: " . $user->id . ")\n\n";

// Найдем заказ OOOL-2604-0003
$order = Order::where('order_number', 'OOOL-2604-0003')->first();
if (!$order) {
    echo "Заказ OOOL-2604-0003 не найден\n";
    exit;
}

echo "Заказ ID: " . $order->id . "\n";
echo "Создатель заказа (manager_id): " . ($order->manager_id ?? 'null') . "\n";
echo "Текущий carrier_id: " . ($order->carrier_id ?? 'null') . "\n";
echo "Текущий performers: " . json_encode($order->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n\n";

// Проверим, является ли пользователь создателем
if ($order->manager_id != $user->id) {
    echo "Внимание: Пользователь ID 32 не является создателем заказа (manager_id = " . ($order->manager_id ?? 'null') . ")\n";
} else {
    echo "✓ Пользователь является создателем заказа\n";
}

// Данные для обновления
$validated = [
    'status' => 'new',
    'own_company_id' => 2367,
    'client_id' => 186,
    'order_date' => '2026-04-07',
    'order_number' => 'OOOL-2604-0003',
    'payment_terms' => '{"client":{"payment_form":"vat","request_mode":"single_request","payment_schedule":{"has_prepayment":"0","prepayment_ratio":"50","prepayment_days":"0","prepayment_mode":"fttn","postpayment_days":"5","postpayment_mode":"ottn"}},"carriers":[{"stage":"Плечо 1","contractor_id":970,"payment_form":"no_vat","payment_schedule":{"has_prepayment":"0","prepayment_ratio":"50","prepayment_days":"0","prepayment_mode":"fttn","postpayment_days":"0","postpayment_mode":"ottn"}}]}',
    'special_notes' => null,
    'performers' => [
        ['stage' => 'Плечо 1', 'contractor_id' => 970]
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
echo "- performers[0].contractor_id: " . $validated['performers'][0]['contractor_id'] . "\n";
echo "- financial_term.contractors_costs[0].contractor_id: " . $validated['financial_term']['contractors_costs'][0]['contractor_id'] . "\n\n";

// Тестируем сервис
$service = app(OrderWizardService::class);

echo "Вызываем OrderWizardService::update()...\n";

try {
    // Сохраняем оригинальные данные для отката
    $originalCarrierId = $order->carrier_id;
    $originalPerformers = $order->performers;
    
    $updatedOrder = $service->update($order, $validated, $user);
    
    echo "\nРезультат обновления:\n";
    echo "carrier_id: " . ($updatedOrder->carrier_id ?? 'null') . " (было: $originalCarrierId)\n";
    echo "performers: " . json_encode($updatedOrder->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    
    if ($updatedOrder->carrier_id == 970) {
        echo "\n✓ УСПЕХ! carrier_id обновлен на 970\n";
    } else {
        echo "\n✗ ОШИБКА: carrier_id не обновлен. Ожидалось: 970, получено: " . ($updatedOrder->carrier_id ?? 'null') . "\n";
    }
    
    // Проверяем financial_terms
    if (method_exists($updatedOrder, 'financialTerms')) {
        $financialTerm = $updatedOrder->financialTerms->first();
        if ($financialTerm) {
            echo "financial_terms.contractors_costs: " . json_encode($financialTerm->contractors_costs ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        }
    }
    
    // Откатываем изменения
    $order->carrier_id = $originalCarrierId;
    $order->performers = $originalPerformers;
    $order->save();
    echo "\n✓ Изменения откатаны\n";
    
} catch (Exception $e) {
    echo "\n✗ ИСКЛЮЧЕНИЕ при обновлении:\n";
    echo "Сообщение: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
    
    // Проверим, есть ли внутренние исключения
    $previous = $e->getPrevious();
    while ($previous) {
        echo "\nВложенное исключение:\n";
        echo "Сообщение: " . $previous->getMessage() . "\n";
        $previous = $previous->getPrevious();
    }
}

echo "\nПроверка завершена.\n";