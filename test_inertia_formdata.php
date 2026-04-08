<?php

// Тест для понимания как Inertia.js отправляет FormData
echo "Тестирование преобразования FormData Inertia.js\n\n";

// Пример данных формы Vue
$formData = [
    'status' => 'new',
    'client_id' => 1,
    'order_date' => '2024-01-01',
    'performers' => [
        ['stage' => 'Плечо 1', 'contractor_id' => 2053],
        ['stage' => 'Плечо 2', 'contractor_id' => null],
    ],
    'financial_term' => [
        'client_price' => 1000,
        'contractors_costs' => [
            ['stage' => 'Плечо 1', 'contractor_id' => 2053, 'amount' => 500],
            ['stage' => 'Плечо 2', 'contractor_id' => null, 'amount' => null],
        ],
    ],
];

echo "Исходные данные формы:\n";
echo json_encode($formData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

// Как FormData преобразует nested массивы
echo "Как FormData преобразует nested массивы:\n";
echo "performers[0][stage] = 'Плечо 1'\n";
echo "performers[0][contractor_id] = 2053\n";
echo "performers[1][stage] = 'Плечо 2'\n";
echo "performers[1][contractor_id] = \n";
echo "financial_term[client_price] = 1000\n";
echo "financial_term[contractors_costs][0][stage] = 'Плечо 1'\n";
echo "financial_term[contractors_costs][0][contractor_id] = 2053\n";
echo "financial_term[contractors_costs][0][amount] = 500\n";
echo "financial_term[contractors_costs][1][stage] = 'Плечо 2'\n";
echo "financial_term[contractors_costs][1][contractor_id] = \n";
echo "financial_term[contractors_costs][1][amount] = \n\n";

// Проверим, может быть Inertia.js автоматически преобразует performers в carriers
echo "Гипотеза: может быть Inertia.js автоматически преобразует performers в carriers?\n";
echo "Проверим документацию Inertia.js...\n\n";

// Проверим, может быть есть кастомная логика в компоненте
echo "Что нужно проверить в Vue компоненте:\n";
echo "1. Есть ли computed property, которое преобразует performers в carriers\n";
echo "2. Есть ли метод transform или prepare данных перед отправкой\n";
echo "3. Есть ли watch, который меняет структуру данных\n";
echo "4. Может быть Inertia.js useForm имеет опции преобразования\n\n";

echo "Проверим поиском в коде:\n";
echo "- 'carriers' как ключ в объекте\n";
echo "- 'carriers' в computed свойствах\n";
echo "- 'carriers' в методах\n";
echo "- Преобразование данных в submit()\n";
