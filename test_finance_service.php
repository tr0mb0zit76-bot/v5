<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Testing FinanceOverviewService...\n";

$service = new App\Services\Finance\FinanceOverviewService();
$result = $service->cashFlowJournal(null, null, "all");

echo "Records count: " . $result->count() . "\n";

if ($result->count() > 0) {
    echo "First record:\n";
    print_r($result->first());
    
    // Проверим структуру данных
    echo "\nChecking data structure...\n";
    $first = $result->first();
    echo "Has order_id: " . (isset($first['order_id']) ? 'YES' : 'NO') . "\n";
    echo "Has order_number: " . (isset($first['order_number']) ? 'YES' : 'NO') . "\n";
    echo "Has amount: " . (isset($first['amount']) ? 'YES' : 'NO') . "\n";
    echo "Has status: " . (isset($first['status']) ? 'YES' : 'NO') . "\n";
    
    // Проверим поля для частичных платежей
    echo "\nChecking partial payment fields...\n";
    echo "Has paid_amount: " . (isset($first['paid_amount']) ? 'YES' : 'NO') . "\n";
    echo "Has remaining_amount: " . (isset($first['remaining_amount']) ? 'YES' : 'NO') . "\n";
    echo "Has is_partial: " . (isset($first['is_partial']) ? 'YES' : 'NO') . "\n";
    echo "Has parent_payment_id: " . (isset($first['parent_payment_id']) ? 'YES' : 'NO') . "\n";
}

// Проверим SQL запрос напрямую
echo "\n\nChecking database directly...\n";
$db = \Illuminate\Support\Facades\DB::table('payment_schedules')->count();
echo "Total payment_schedules records: " . $db . "\n";

// Проверим структуру таблицы
echo "\nChecking table structure...\n";
$columns = \Illuminate\Support\Facades\Schema::getColumnListing('payment_schedules');
echo "Columns in payment_schedules: " . implode(', ', $columns) . "\n";

// Проверим наличие новых полей
$newFields = ['paid_amount', 'remaining_amount', 'parent_payment_id', 'payment_method', 'transaction_reference', 'is_partial'];
foreach ($newFields as $field) {
    $hasField = in_array($field, $columns);
    echo "Has $field: " . ($hasField ? 'YES' : 'NO') . "\n";
}