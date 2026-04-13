<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing FinanceIndexController with authenticated user...\n";

// Создаем тестового пользователя
$user = \App\Models\User::first();
if (!$user) {
    echo "No users found in database\n";
    exit(1);
}

echo "Using user: {$user->name} (ID: {$user->id}, Role ID: {$user->role_id})\n";

// Аутентифицируем пользователя
auth()->login($user);

// Создаем мок запроса
$request = new \Illuminate\Http\Request();
$request->query->set('section', 'cashflow');
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Создаем контроллер
$controller = new \App\Http\Controllers\FinanceIndexController();
$service = new \App\Services\Finance\FinanceOverviewService();

// Получаем ответ
$response = $controller($request, $service);

echo "\nResponse type: " . get_class($response) . "\n";

// Проверим, что возвращается
if ($response instanceof \Inertia\Response) {
    // Получим props через рефлексию
    $reflection = new ReflectionClass($response);
    $propsProperty = $reflection->getProperty('props');
    $propsProperty->setAccessible(true);
    $props = $propsProperty->getValue($response);
    
    echo "\nProps keys: " . implode(', ', array_keys($props)) . "\n";
    
    if (isset($props['cashFlowJournal'])) {
        $count = is_countable($props['cashFlowJournal']) ? count($props['cashFlowJournal']) : 0;
        echo "cashFlowJournal count: $count\n";
        
        if ($count > 0) {
            echo "\nFirst record structure:\n";
            $first = is_array($props['cashFlowJournal']) ? $props['cashFlowJournal'][0] : $props['cashFlowJournal']->first();
            print_r($first);
            
            // Проверим наличие необходимых полей
            $requiredFields = ['id', 'order_id', 'order_number', 'amount', 'status', 'direction', 'counterparty_name', 'payment_type'];
            foreach ($requiredFields as $field) {
                $has = isset($first[$field]) ? 'YES' : 'NO';
                echo "Has $field: $has\n";
            }
        }
    } else {
        echo "ERROR: cashFlowJournal not found in props!\n";
        echo "Available props: " . json_encode(array_keys($props)) . "\n";
    }
    
    // Проверим другие важные пропсы
    echo "\nChecking other important props:\n";
    $importantProps = ['active_submodule', 'summary', 'todays_cash_flow', 'cash_flow_stats', 'can_access_salary_module'];
    foreach ($importantProps as $prop) {
        $has = isset($props[$prop]) ? 'YES' : 'NO';
        echo "Has $prop: $has\n";
        if ($has === 'YES' && in_array($prop, ['summary', 'todays_cash_flow', 'cash_flow_stats'])) {
            echo "  Value: " . json_encode($props[$prop], JSON_PRETTY_PRINT) . "\n";
        }
    }
} else {
    echo "Response is not Inertia Response\n";
    print_r($response);
}