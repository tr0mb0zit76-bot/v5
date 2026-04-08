<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Order;
use App\Models\User;
use App\Services\OrderWizardService;

echo "Тестирование сохранения contractor_id\n\n";

// Создаем тестовые данные, аналогичные тем, что отправляет фронтенд
$validated = [
    'status' => 'new',
    'own_company_id' => 2367,
    'client_id' => 186,
    'order_date' => '2026-04-07',
    'order_number' => 'OOOL-2604-0003',
    'payment_terms' => '{"client":{"payment_form":"vat","request_mode":"single_request","payment_schedule":{"has_prepayment":"0","prepayment_ratio":"50","prepayment_days":"0","prepayment_mode":"fttn","postpayment_days":"5","postpayment_mode":"ottn"}},"carriers":[{"stage":"Плечо 1","contractor_id":2053,"payment_form":"no_vat","payment_schedule":{"has_prepayment":"0","prepayment_ratio":"50","prepayment_days":"0","prepayment_mode":"fttn","postpayment_days":"0","postpayment_mode":"ottn"}}]}',
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

echo "Данные для сохранения:\n";
echo "performers[0].contractor_id = " . $validated['performers'][0]['contractor_id'] . "\n";
echo "financial_term.contractors_costs[0].contractor_id = " . $validated['financial_term']['contractors_costs'][0]['contractor_id'] . "\n\n";

// Тестируем метод extractOrderAttributes
$service = app(OrderWizardService::class);

// Используем рефлексию для вызова приватного метода
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('extractOrderAttributes');
$method->setAccessible(true);

// Создаем реального пользователя
$user = User::find(1);
if (!$user) {
    $user = new User();
    $user->id = 1;
    $user->name = 'Test User';
}

$numberData = ['company_code' => 'OOOL', 'order_number' => '2604-0003'];

try {
    $attributes = $method->invoke($service, $validated, $user, $numberData, false);
    
    echo "Результат extractOrderAttributes:\n";
    echo "carrier_id = " . ($attributes['carrier_id'] ?? 'null') . "\n";
    echo "performers = " . json_encode($attributes['performers'] ?? [], JSON_UNESCAPED_UNICODE) . "\n\n";
    
    // Проверяем, что contractor_id нормализован
    if (isset($attributes['performers'][0]['contractor_id'])) {
        echo "performers[0].contractor_id после нормализации = " . $attributes['performers'][0]['contractor_id'] . "\n";
        echo "Тип: " . gettype($attributes['performers'][0]['contractor_id']) . "\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

// Тестируем метод syncContractorsCostsWithPerformers
echo "\nТестирование syncContractorsCostsWithPerformers:\n";

$method2 = $reflection->getMethod('syncContractorsCostsWithPerformers');
$method2->setAccessible(true);

$contractorsCosts = $validated['financial_term']['contractors_costs'];
$performers = $validated['performers'];

try {
    $result = $method2->invoke($service, $contractorsCosts, $performers);
    
    echo "До синхронизации:\n";
    echo "contractors_costs[0].contractor_id = " . $contractorsCosts[0]['contractor_id'] . "\n";
    echo "performers[0].contractor_id = " . $performers[0]['contractor_id'] . "\n\n";
    
    echo "После синхронизации:\n";
    echo "contractors_costs[0].contractor_id = " . $result[0]['contractor_id'] . "\n\n";
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}

echo "\nПроверка завершена.\n";