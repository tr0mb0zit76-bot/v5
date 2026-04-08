<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\User;
use App\Http\Controllers\Orders\OrderWizardController;
use Illuminate\Http\Request;

echo "Тестирование реального запроса на обновление заказа\n\n";

// Найдем заказ OOOL-2604-0003
$order = Order::where('order_number', 'OOOL-2604-0003')->first();
if (!$order) {
    echo "Заказ OOOL-2604-0003 не найден\n";
    exit;
}

echo "Текущее состояние заказа ID: " . $order->id . "\n";
echo "carrier_id: " . ($order->carrier_id ?? 'null') . "\n";
echo "performers: " . json_encode($order->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n\n";

// Создаем мок запроса с данными, которые отправляет фронтенд
$requestData = [
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

echo "Данные запроса:\n";
echo "- performers[0].contractor_id: " . $requestData['performers'][0]['contractor_id'] . "\n";
echo "- financial_term.contractors_costs[0].contractor_id: " . $requestData['financial_term']['contractors_costs'][0]['contractor_id'] . "\n";
echo "- payment_terms (декодировано): ";
$paymentTerms = json_decode($requestData['payment_terms'], true);
echo "carriers[0].contractor_id: " . ($paymentTerms['carriers'][0]['contractor_id'] ?? 'null') . "\n\n";

// Проверяем валидацию
echo "Проверка валидации:\n";
$request = new Request($requestData);

// Создаем экземпляр контроллера
$controller = new OrderWizardController();

// Получаем пользователя
$user = User::find(1);
if (!$user) {
    echo "Пользователь не найден. Создаем тестового...\n";
    $user = new User();
    $user->id = 1;
    $user->name = 'Test User';
    $user->email = 'test@example.com';
    $user->password = bcrypt('password');
}

// Устанавливаем пользователя в запрос
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Проверяем, проходит ли валидация
try {
    echo "Создаем FormRequest...\n";
    
    // Создаем FormRequest вручную
    $formRequest = \App\Http\Requests\UpdateOrderRequest::createFrom($request);
    $formRequest->setContainer(app());
    $formRequest->setRedirector(app('redirect'));
    
    // Выполняем валидацию
    $validated = $formRequest->validated();
    
    echo "✓ Валидация прошла успешно\n";
    echo "validated['performers'][0]['contractor_id']: " . ($validated['performers'][0]['contractor_id'] ?? 'null') . "\n\n";
    
    // Теперь тестируем сервис
    echo "Тестируем OrderWizardService::update()...\n";
    $service = app(\App\Services\OrderWizardService::class);
    
    // Сохраняем текущее состояние для отката
    $originalCarrierId = $order->carrier_id;
    $originalPerformers = $order->performers;
    
    try {
        $updatedOrder = $service->update($order, $validated, $user);
        
        echo "\nРезультат обновления:\n";
        echo "carrier_id: " . ($updatedOrder->carrier_id ?? 'null') . " (было: $originalCarrierId)\n";
        echo "performers: " . json_encode($updatedOrder->performers ?? [], JSON_UNESCAPED_UNICODE) . "\n";
        
        if ($updatedOrder->carrier_id == 970) {
            echo "✓ carrier_id успешно обновлен на 970\n";
        } else {
            echo "✗ carrier_id не обновлен. Ожидалось: 970, получено: " . ($updatedOrder->carrier_id ?? 'null') . "\n";
        }
        
        // Откатываем изменения для теста
        $order->carrier_id = $originalCarrierId;
        $order->performers = $originalPerformers;
        $order->save();
        
    } catch (Exception $e) {
        echo "Ошибка в service->update(): " . $e->getMessage() . "\n";
    }
    
} catch (Illuminate\Validation\ValidationException $e) {
    echo "✗ Ошибка валидации: " . $e->getMessage() . "\n";
    echo "Ошибки: " . json_encode($e->errors(), JSON_UNESCAPED_UNICODE) . "\n";
} catch (Exception $e) {
    echo "✗ Другая ошибка: " . $e->getMessage() . "\n";
}

echo "\nПроверка завершена.\n";