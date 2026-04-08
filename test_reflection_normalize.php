<?php

require_once __DIR__.'/vendor/autoload.php';

use App\Http\Controllers\Orders\OrderWizardController;
use App\Models\FinancialTerm;
use App\Models\Order;

// Создаем экземпляр контроллера
$controller = new OrderWizardController;

// Используем рефлексию для доступа к приватному методу
$reflection = new ReflectionClass(OrderWizardController::class);
$method = $reflection->getMethod('normalizeContractorsCosts');
$method->setAccessible(true);

// Тест 1: Проверяем, что при пустом performers и carrier_id не null возвращается пустой массив
echo "Тест 1: Проверка исправления - пустой performers, carrier_id не null\n";

$order1 = new Order;
$order1->performers = [];
$order1->carrier_id = 123; // carrier_id не null
$order1->carrier_rate = 1000;
$order1->carrier_payment_form = 'no_vat';

$financialTerm1 = null;

$result1 = $method->invoke($controller, $order1, $financialTerm1);

echo 'Результат: '.json_encode($result1, JSON_PRETTY_PRINT)."\n";
echo "Ожидаем: [] (пустой массив)\n";
echo 'Тест пройден: '.(empty($result1) ? '✓' : '✗')."\n\n";

// Тест 2: Проверяем, что при пустом performers и carrier_rate не null возвращается пустой массив
echo "Тест 2: Проверка исправления - пустой performers, carrier_rate не null\n";

$order2 = new Order;
$order2->performers = [];
$order2->carrier_id = null;
$order2->carrier_rate = 1000; // carrier_rate не null
$order2->carrier_payment_form = 'no_vat';

$financialTerm2 = null;

$result2 = $method->invoke($controller, $order2, $financialTerm2);

echo 'Результат: '.json_encode($result2, JSON_PRETTY_PRINT)."\n";
echo "Ожидаем: [] (пустой массив)\n";
echo 'Тест пройден: '.(empty($result2) ? '✓' : '✗')."\n\n";

// Тест 3: Проверяем, что при наличии performers используется их данные
echo "Тест 3: Проверка - есть performers\n";

$order3 = new Order;
$order3->performers = [
    ['stage' => 'leg_1', 'contractor_id' => 456],
    ['stage' => 'leg_2', 'contractor_id' => 789],
];
$order3->carrier_id = 123;
$order3->carrier_rate = 2000;
$order3->carrier_payment_form = 'vat';

$financialTerm3 = new FinancialTerm;
$financialTerm3->client_currency = 'USD';

$result3 = $method->invoke($controller, $order3, $financialTerm3);

echo 'Результат: '.json_encode($result3, JSON_PRETTY_PRINT)."\n";
echo "Ожидаем: массив с 2 элементами (leg_1 и leg_2)\n";
echo 'Тест пройден: '.(count($result3) === 2 ? '✓' : '✗')."\n\n";

// Тест 4: Проверяем, что при пустом performers и пустом carrier_id/carrier_rate возвращается пустой массив
echo "Тест 4: Проверка - пустой performers, carrier_id и carrier_rate null\n";

$order4 = new Order;
$order4->performers = [];
$order4->carrier_id = null;
$order4->carrier_rate = null;
$order4->carrier_payment_form = 'no_vat';

$financialTerm4 = null;

$result4 = $method->invoke($controller, $order4, $financialTerm4);

echo 'Результат: '.json_encode($result4, JSON_PRETTY_PRINT)."\n";
echo "Ожидаем: [] (пустой массив)\n";
echo 'Тест пройден: '.(empty($result4) ? '✓' : '✗')."\n\n";

// Тест 5: Проверяем, что при наличии contractors_costs в financialTerm они используются
echo "Тест 5: Проверка - есть contractors_costs в financialTerm\n";

$order5 = new Order;
$order5->performers = [];
$order5->carrier_id = null;
$order5->carrier_rate = null;
$order5->carrier_payment_form = 'no_vat';

$financialTerm5 = new FinancialTerm;
$financialTerm5->contractors_costs = [
    ['stage' => 'leg_1', 'contractor_id' => 999, 'amount' => 500, 'currency' => 'RUB', 'payment_form' => 'vat', 'payment_schedule' => []],
];
$financialTerm5->client_currency = 'RUB';

$result5 = $method->invoke($controller, $order5, $financialTerm5);

echo 'Результат: '.json_encode($result5, JSON_PRETTY_PRINT)."\n";
echo "Ожидаем: массив с 1 элементом (leg_1 с contractor_id 999)\n";
echo 'Тест пройден: '.(! empty($result5) && $result5[0]['contractor_id'] === 999 ? '✓' : '✗')."\n";

echo "\n=== ИТОГ ===\n";
echo "Все тесты проверяют, что contractor_id больше не восстанавливается из carrier_id\n";
echo "при пустом массиве performers.\n";
