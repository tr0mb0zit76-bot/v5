<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use App\Models\Order;
use App\Models\PaymentSchedule;
use App\Models\User;
use App\Services\Finance\FinanceOverviewService;

echo "=== Тестирование функционала частичных платежей ===\n\n";

// Создаем тестового пользователя
$user = User::first();
if (!$user) {
    echo "Создаем тестового пользователя...\n";
    $user = User::factory()->create([
        'name' => 'Тестовый пользователь',
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);
}

// Создаем тестовый заказ
$order = Order::first();
if (!$order) {
    echo "Создаем тестовый заказ...\n";
    $order = Order::factory()->create([
        'order_number' => 'TEST-' . time(),
        'manager_id' => $user->id,
    ]);
}

echo "Тестовый заказ: #{$order->order_number}\n";

// Создаем тестовый график платежей
$paymentSchedule = PaymentSchedule::first();
if (!$paymentSchedule) {
    echo "Создаем тестовый график платежей...\n";
    $paymentSchedule = PaymentSchedule::create([
        'order_id' => $order->id,
        'party' => 'customer',
        'type' => 'prepayment',
        'amount' => 10000.00,
        'planned_date' => now()->format('Y-m-d'),
        'status' => 'pending',
        'paid_amount' => 0,
        'remaining_amount' => 10000.00,
        'is_partial' => false,
    ]);
}

echo "Тестовый платеж: ID {$paymentSchedule->id}, сумма: {$paymentSchedule->amount}\n\n";

// Тестируем сервис FinanceOverviewService
echo "=== Тестирование FinanceOverviewService ===\n";

$service = new FinanceOverviewService();

// Тестируем cashFlowJournal
echo "1. Тестируем cashFlowJournal...\n";
$journal = $service->cashFlowJournal($user->id, 'admin', 'all');
echo "   Найдено записей: " . $journal->count() . "\n";

if ($journal->count() > 0) {
    $firstRow = $journal->first();
    echo "   Первая запись:\n";
    echo "   - ID: {$firstRow['id']}\n";
    echo "   - Заказ: {$firstRow['order_number']}\n";
    echo "   - Сумма: {$firstRow['amount']}\n";
    echo "   - Статус: {$firstRow['status']}\n";
    echo "   - Оплачено: {$firstRow['paid_amount']}\n";
    echo "   - Остаток: {$firstRow['remaining_amount']}\n";
    echo "   - Прогресс: {$firstRow['payment_progress']}%\n";
    echo "   - Частичные платежи: " . ($firstRow['has_partial_payments'] ? 'да' : 'нет') . "\n";
}

// Тестируем cashFlowStats
echo "\n2. Тестируем cashFlowStats...\n";
$stats = $service->cashFlowStats($user->id, 'admin', 'all');
echo "   Статистика по периодам:\n";
echo "   - Сегодня (входящие): {$stats['periods']['today']['incoming']}\n";
echo "   - Сегодня (исходящие): {$stats['periods']['today']['outgoing']}\n";
echo "   - Дебиторка (всего): {$stats['receivables']['total']}\n";
echo "   - Кредиторка (всего): {$stats['payables']['total']}\n";

// Тестируем контроллер PaymentScheduleController
echo "\n=== Тестирование PaymentScheduleController ===\n";

use App\Http\Controllers\PaymentScheduleController;

$controller = new PaymentScheduleController();

// Создаем тестовый запрос для фиксации платежа
echo "1. Тестируем фиксацию частичного платежа...\n";

$paymentData = [
    'amount' => 5000.00,
    'actual_date' => now()->format('Y-m-d'),
    'payment_method' => 'bank_transfer',
    'transaction_reference' => 'TEST-' . time(),
    'notes' => 'Тестовый частичный платеж',
];

echo "   Данные платежа:\n";
echo "   - Сумма: {$paymentData['amount']}\n";
echo "   - Дата: {$paymentData['actual_date']}\n";
echo "   - Способ: {$paymentData['payment_method']}\n";

// Проверяем, что платеж можно зафиксировать
echo "\n2. Проверяем статус платежа...\n";
echo "   Текущий статус: {$paymentSchedule->status}\n";
echo "   Оплачено: {$paymentSchedule->paid_amount}\n";
echo "   Остаток: {$paymentSchedule->remaining_amount}\n";

// Тестируем модель PaymentSchedule
echo "\n=== Тестирование модели PaymentSchedule ===\n";

echo "1. Проверяем методы модели:\n";
echo "   - isFullyPaid: " . ($paymentSchedule->isFullyPaid() ? 'да' : 'нет') . "\n";
echo "   - hasPartialPayments: " . ($paymentSchedule->hasPartialPayments() ? 'да' : 'нет') . "\n";

// Создаем частичный платеж для теста
echo "\n2. Создаем тестовый частичный платеж...\n";
$partialPayment = PaymentSchedule::create([
    'order_id' => $order->id,
    'party' => 'customer',
    'type' => 'prepayment',
    'amount' => 3000.00,
    'planned_date' => now()->format('Y-m-d'),
    'actual_date' => now()->format('Y-m-d'),
    'status' => 'paid',
    'paid_amount' => 3000.00,
    'remaining_amount' => 0,
    'is_partial' => true,
    'parent_payment_id' => $paymentSchedule->id,
    'payment_method' => 'cash',
    'transaction_reference' => 'PARTIAL-' . time(),
]);

echo "   Создан частичный платеж ID: {$partialPayment->id}\n";
echo "   Сумма: {$partialPayment->amount}\n";
echo "   Статус: {$partialPayment->status}\n";

// Обновляем родительский платеж
$paymentSchedule->refresh();
echo "\n3. Проверяем обновление родительского платежа:\n";
echo "   Оплачено: {$paymentSchedule->paid_amount}\n";
echo "   Остаток: {$paymentSchedule->remaining_amount}\n";

// Тестируем обновление статуса заказа
echo "\n=== Тестирование обновления статуса заказа ===\n";

$order->refresh();
echo "Текущий статус оплаты заказа: " . ($order->payment_status ?? 'не установлен') . "\n";

// Если платеж полностью оплачен, проверяем обновление статуса
if ($paymentSchedule->isFullyPaid()) {
    echo "Платеж полностью оплачен, статус заказа должен обновиться.\n";
}

echo "\n=== Тестирование завершено ===\n";

// Очистка тестовых данных (опционально)
echo "\nОчистка тестовых данных...\n";
$partialPayment->delete();
// $paymentSchedule->delete(); // Оставляем для демонстрации

echo "Готово!\n";