<?php

// Тест для проверки данных формы
echo "Тестирование данных формы\n";

// Проверим, что форма содержит performers
$testData = [
    'status' => 'new',
    'client_id' => 1,
    'order_date' => '2024-01-01',
    'performers' => [
        ['stage' => 'Плечо 1', 'contractor_id' => 2053],
    ],
    'financial_term' => [
        'client_price' => 1000,
        'contractors_costs' => [
            ['stage' => 'Плечо 1', 'contractor_id' => 2053, 'amount' => 500],
        ],
    ],
];

echo "Ожидаемые данные (сервер ожидает):\n";
echo json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

// Данные, которые отправляет фронтенд (из summary)
$frontendData = [
    'client' => ['id' => 1, 'name' => 'Test Client'],
    'carriers' => [
        ['stage' => 'Плечо 1', 'contractor_id' => 2053],
    ],
];

echo "Данные от фронтенда (проблема):\n";
echo json_encode($frontendData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

echo "Проблема: фронтенд отправляет поле 'carriers', а сервер ожидает 'performers'\n";
echo "Решение: нужно исправить Vue компонент, чтобы он отправлял 'performers' вместо 'carriers'\n";

// Проверим структуру performers
echo "\nСтруктура performers (ожидаемая сервером):\n";
echo "- Массив объектов\n";
echo "- Каждый объект имеет stage и contractor_id\n";
echo "- stage: строка (например 'Плечо 1', 'leg_1')\n";
echo "- contractor_id: integer или null\n";
