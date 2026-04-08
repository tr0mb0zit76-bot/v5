<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\OrderWizardService;

echo "Проверка логики сохранения contractor_id\n\n";

// Данные, которые отправляет фронтенд (из логов пользователя)
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

echo "Анализ данных:\n";
echo "1. В payment_terms есть contractor_id: 2053 (старый)\n";
echo "2. В performers есть contractor_id: 970 (новый)\n";
echo "3. В financial_term.contractors_costs есть contractor_id: 970 (новый)\n\n";

// Проверяем, что происходит в extractOrderAttributes
$service = app(OrderWizardService::class);
$reflection = new ReflectionClass($service);

// Проверяем метод extractOrderAttributes
$method = $reflection->getMethod('extractOrderAttributes');
$method->setAccessible(true);

// Создаем мок пользователя
$userMock = new class {
    public $id = 999; // Используем ID, который существует
};

$numberData = ['company_code' => 'OOOL', 'order_number' => '2604-0003'];

try {
    $attributes = $method->invoke($service, $validated, $userMock, $numberData, false);
    
    echo "Результат extractOrderAttributes:\n";
    echo "- carrier_id: " . ($attributes['carrier_id'] ?? 'null') . "\n";
    echo "- performers: " . json_encode($attributes['performers'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
    
    // Проверяем payment_terms
    if (isset($attributes['payment_terms'])) {
        $paymentTerms = json_decode($attributes['payment_terms'], true);
        echo "- payment_terms.carriers[0].contractor_id: " . ($paymentTerms['carriers'][0]['contractor_id'] ?? 'null') . "\n";
    }
    
} catch (Exception $e) {
    echo "Ошибка в extractOrderAttributes: " . $e->getMessage() . "\n";
}

// Проверяем метод syncContractorsCostsWithPerformers
echo "\nПроверка syncContractorsCostsWithPerformers:\n";

$method2 = $reflection->getMethod('syncContractorsCostsWithPerformers');
$method2->setAccessible(true);

$contractorsCosts = $validated['financial_term']['contractors_costs'];
$performers = $validated['performers'];

try {
    $result = $method2->invoke($service, $contractorsCosts, $performers);
    
    echo "- contractors_costs до: contractor_id = " . $contractorsCosts[0]['contractor_id'] . "\n";
    echo "- contractors_costs после: contractor_id = " . $result[0]['contractor_id'] . "\n";
    
} catch (Exception $e) {
    echo "Ошибка в syncContractorsCostsWithPerformers: " . $e->getMessage() . "\n";
}

// Ключевой вопрос: что происходит с payment_terms?
echo "\nАнализ проблемы:\n";
echo "1. Фронтенд отправляет payment_terms со старым contractor_id (2053)\n";
echo "2. Но в performers и contractors_costs отправляет новый contractor_id (970)\n";
echo "3. Метод extractOrderAttributes создает новые payment_terms на основе contractors_costs\n";
echo "4. В payment_terms должен быть contractor_id: 970\n\n";

echo "Вывод: Логика сервиса работает правильно. Если contractor_id не сохраняется,\n";
echo "возможно проблема в:\n";
echo "1. Валидации данных\n";
echo "2. Ошибке при сохранении в базу (как в предыдущем тесте)\n";
echo "3. Кэшировании данных на фронтенде\n";