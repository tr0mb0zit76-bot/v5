<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing FinanceIndexController response...\n";

// Создаем мок запроса
$request = new \Illuminate\Http\Request();
$request->query->set('section', 'cashflow');

// Создаем контроллер
$controller = new \App\Http\Controllers\FinanceIndexController();
$service = new \App\Services\Finance\FinanceOverviewService();

// Получаем ответ
$response = $controller($request, $service);

echo "Response type: " . get_class($response) . "\n";

if ($response instanceof \Inertia\Response) {
    $props = $response->getProps();
    echo "\nProps keys: " . implode(', ', array_keys($props)) . "\n";
    
    echo "\nChecking cashFlowJournal...\n";
    $cashFlow = $props['cashFlowJournal'] ?? [];
    echo "cashFlowJournal count: " . count($cashFlow) . "\n";
    
    if (count($cashFlow) > 0) {
        echo "First record structure:\n";
        print_r($cashFlow[0]);
        
        // Проверим наличие необходимых полей
        $requiredFields = ['id', 'order_id', 'order_number', 'amount', 'status', 'direction', 'counterparty_name', 'payment_type'];
        foreach ($requiredFields as $field) {
            echo "Has $field: " . (isset($cashFlow[0][$field]) ? 'YES' : 'NO') . "\n";
        }
    }
    
    echo "\nChecking active_submodule: " . ($props['active_submodule'] ?? 'NOT SET') . "\n";
    echo "Checking summary: " . json_encode($props['summary'] ?? [], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "Response is not Inertia Response\n";
    print_r($response);
}