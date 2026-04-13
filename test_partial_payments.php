<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\PaymentSchedule;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;

echo "=== Тестирование функционала частичных платежей ===\n\n";

try {
    // Создаем тестового пользователя
    $user = User::first();
    if (!$user) {
        echo "Создаем тестового пользователя...\n";
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }
    
    auth()->login($user);
    
    // Создаем тестовый заказ
    echo "Создаем тестовый заказ...\n";
    $order = Order::first();
    if (!$order) {
        $order = Order::factory()->create([
            'order_number' => 'TEST-' . time(),
            'status' => 'active',
            'payment_status' => 'pending',
        ]);
    }
    
    // Создаем тестовый график платежей
    echo "Создаем тестовый график платежей...\n";
    $paymentSchedule = PaymentSchedule::create([
        'order_id' => $order->id,
        'party' => 'customer',
        'type' => 'final',
        'amount' => 10000.00,
        'paid_amount' => 0,
        'remaining_amount' => 10000.00,
        'planned_date' => now()->addDays(30),
        'status' => 'pending',
        'notes' => 'Тестовый платеж',
    ]);
    
    echo "Создан платеж ID: {$paymentSchedule->id}\n";
    echo "Сумма: {$paymentSchedule->amount}\n";
    echo "Статус: {$paymentSchedule->status}\n\n";
    
    // Тест 1: Первый частичный платеж (5000 из 10000)
    echo "=== Тест 1: Первый частичный платеж (5000 из 10000) ===\n";
    
    $requestData = [
        'paid_amount' => 5000.00,
        'payment_method' => 'bank_transfer',
        'transaction_reference' => 'TRX-' . time(),
        'payment_date' => now()->format('Y-m-d'),
        'notes' => 'Первый частичный платеж',
    ];
    
    $controller = new \App\Http\Controllers\PaymentScheduleController();
    $request = new \Illuminate\Http\Request($requestData);
    
    $response = $controller->recordPayment($request, $paymentSchedule);
    $result = json_decode($response->getContent(), true);
    
    if ($result['success']) {
        echo "✓ Платеж успешно зарегистрирован\n";
        $updatedPayment = $result['payment_schedule'];
        echo "  Оплачено: {$updatedPayment['paid_amount']}\n";
        echo "  Остаток: {$updatedPayment['remaining_amount']}\n";
        echo "  Статус: {$updatedPayment['status']}\n";
        
        if (isset($result['partial_payment'])) {
            echo "  Создан частичный платеж ID: {$result['partial_payment']['id']}\n";
        }
    } else {
        echo "✗ Ошибка: {$result['message']}\n";
    }
    
    echo "\n";
    
    // Обновляем объект из базы
    $paymentSchedule->refresh();
    
    // Тест 2: Второй частичный платеж (3000 из оставшихся 5000)
    echo "=== Тест 2: Второй частичный платеж (3000 из оставшихся 5000) ===\n";
    
    $requestData = [
        'paid_amount' => 3000.00,
        'payment_method' => 'cash',
        'transaction_reference' => 'TRX-' . (time() + 1),
        'payment_date' => now()->format('Y-m-d'),
        'notes' => 'Второй частичный платеж',
    ];
    
    $request = new \Illuminate\Http\Request($requestData);
    $response = $controller->recordPayment($request, $paymentSchedule);
    $result = json_decode($response->getContent(), true);
    
    if ($result['success']) {
        echo "✓ Платеж успешно зарегистрирован\n";
        $updatedPayment = $result['payment_schedule'];
        echo "  Оплачено: {$updatedPayment['paid_amount']}\n";
        echo "  Остаток: {$updatedPayment['remaining_amount']}\n";
        echo "  Статус: {$updatedPayment['status']}\n";
        
        if (isset($result['partial_payment'])) {
            echo "  Создан частичный платеж ID: {$result['partial_payment']['id']}\n";
        }
    } else {
        echo "✗ Ошибка: {$result['message']}\n";
    }
    
    echo "\n";
    
    // Тест 3: Полная оплата оставшейся суммы (2000)
    echo "=== Тест 3: Полная оплата оставшейся суммы (2000) ===\n";
    
    $paymentSchedule->refresh();
    $requestData = [
        'paid_amount' => 2000.00,
        'payment_method' => 'card',
        'transaction_reference' => 'TRX-' . (time() + 2),
        'payment_date' => now()->format('Y-m-d'),
        'notes' => 'Финальный платеж',
    ];
    
    $request = new \Illuminate\Http\Request($requestData);
    $response = $controller->recordPayment($request, $paymentSchedule);
    $result = json_decode($response->getContent(), true);
    
    if ($result['success']) {
        echo "✓ Платеж успешно зарегистрирован\n";
        $updatedPayment = $result['payment_schedule'];
        echo "  Оплачено: {$updatedPayment['paid_amount']}\n";
        echo "  Остаток: {$updatedPayment['remaining_amount']}\n";
        echo "  Статус: {$updatedPayment['status']}\n";
        
        if (isset($result['partial_payment'])) {
            echo "  Создан частичный платеж ID: {$result['partial_payment']['id']}\n";
        }
        
        // Проверяем статус заказа
        $order->refresh();
        echo "  Статус оплаты заказа: {$order->payment_status}\n";
    } else {
        echo "✗ Ошибка: {$result['message']}\n";
    }
    
    echo "\n";
    
    // Тест 4: Получение списка частичных платежей
    echo "=== Тест 4: Получение списка частичных платежей ===\n";
    
    $response = $controller->getPartialPayments($paymentSchedule);
    $result = json_decode($response->getContent(), true);
    
    if ($result['success']) {
        $partialPayments = $result['partial_payments'];
        echo "✓ Найдено частичных платежей: " . count($partialPayments) . "\n";
        
        foreach ($partialPayments as $index => $partial) {
            echo "  {$index}. ID: {$partial['id']}, Сумма: {$partial['amount']}, Метод: {$partial['payment_method']}\n";
        }
    } else {
        echo "✗ Ошибка при получении частичных платежей\n";
    }
    
    echo "\n";
    
    // Тест 5: Отмена и восстановление платежа
    echo "=== Тест 5: Отмена и восстановление платежа ===\n";
    
    // Создаем новый платеж для теста отмены
    $testPayment = PaymentSchedule::create([
        'order_id' => $order->id,
        'party' => 'carrier',
        'type' => 'prepayment',
        'amount' => 5000.00,
        'paid_amount' => 0,
        'remaining_amount' => 5000.00,
        'planned_date' => now()->addDays(15),
        'status' => 'pending',
        'notes' => 'Тестовый платеж для отмены',
    ]);
    
    echo "Создан платеж для отмены ID: {$testPayment->id}\n";
    
    // Отменяем платеж
    $response = $controller->cancel($testPayment);
    $result = json_decode($response->getContent(), true);
    
    if ($result['success']) {
        echo "✓ Платеж отменен\n";
        echo "  Новый статус: {$result['payment_schedule']['status']}\n";
        
        // Восстанавливаем платеж
        $response = $controller->restore($testPayment);
        $result = json_decode($response->getContent(), true);
        
        if ($result['success']) {
            echo "✓ Платеж восстановлен\n";
            echo "  Новый статус: {$result['payment_schedule']['status']}\n";
        } else {
            echo "✗ Ошибка при восстановлении: {$result['message']}\n";
        }
    } else {
        echo "✗ Ошибка при отмене: {$result['message']}\n";
    }
    
    echo "\n=== Тестирование завершено ===\n";
    
} catch (\Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Трейс:\n" . $e->getTraceAsString() . "\n";
}