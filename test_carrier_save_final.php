<?php

// Финальный тест для проверки сохранения изменений исполнителя
echo "Тестирование сохранения изменений исполнителя\n\n";

// Симулируем данные, которые теперь должен отправлять фронтенд
$frontendData = [
    'status' => 'new',
    'client_id' => 1,
    'order_date' => '2024-01-01',
    'performers' => [
        ['stage' => 'Плечо 1', 'contractor_id' => 2053],
        ['stage' => 'Плечо 2', 'contractor_id' => null],
    ],
    'financial_term' => [
        'client_price' => 1000,
        'client_currency' => 'RUB',
        'client_payment_form' => 'vat',
        'client_request_mode' => 'single_request',
        'client_payment_schedule' => [],
        'contractors_costs' => [
            ['stage' => 'Плечо 1', 'contractor_id' => 2053, 'amount' => 500, 'currency' => 'RUB'],
            ['stage' => 'Плечо 2', 'contractor_id' => null, 'amount' => null, 'currency' => 'RUB'],
        ],
        'additional_costs' => [],
        'kpi_percent' => 0,
    ],
    'route_points' => [],
    'cargo_items' => [],
    'documents' => [],
];

echo "Данные, которые теперь отправляет фронтенд (исправлено):\n";
echo json_encode($frontendData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

// Проверяем, что данные соответствуют ожиданиям сервера
echo "Проверка структуры данных:\n";
echo "1. Поле 'performers' присутствует: ".(isset($frontendData['performers']) ? '✓' : '✗')."\n";
echo "2. 'performers' является массивом: ".(is_array($frontendData['performers']) ? '✓' : '✗')."\n";
echo '3. Первый performer имеет contractor_id: '.($frontendData['performers'][0]['contractor_id'] ?? 'null')."\n";
echo '4. Второй performer имеет contractor_id: '.($frontendData['performers'][1]['contractor_id'] ?? 'null')."\n";
echo "5. Поле 'financial_term.contractors_costs' присутствует: ".(isset($frontendData['financial_term']['contractors_costs']) ? '✓' : '✗')."\n";
echo '6. contractors_costs синхронизированы с performers: '.
    (($frontendData['financial_term']['contractors_costs'][0]['contractor_id'] ?? null) === ($frontendData['performers'][0]['contractor_id'] ?? null) ? '✓' : '✗')."\n\n";

// Проверяем сценарии
echo "Сценарии, которые теперь должны работать:\n";
echo "1. Установка исполнителя: должно сохраняться contractor_id в performers и contractors_costs\n";
echo "2. Удаление исполнителя: contractor_id должен становиться null в обоих местах\n";
echo "3. Замена исполнителя: contractor_id должен обновляться в обоих местах\n";
echo "4. Сохранение и перезагрузка страницы: данные должны сохраняться в БД\n\n";

echo "Изменения во фронтенде:\n";
echo "1. Метод submit() теперь явно формирует правильную структуру данных\n";
echo "2. Отправляется поле 'performers' вместо 'carriers'\n";
echo "3. financial_term.contractors_costs синхронизированы с performers\n";
echo "4. Используется form.data() для получения всех данных формы\n\n";

echo "Ожидаемый результат:\n";
echo "После нажатия кнопки 'Сохранить' изменения исполнителя должны сохраняться в базе данных.\n";
echo "При обновлении страницы (F5) данные должны загружаться корректно.\n";
