<?php

// Тестируем формат данных, который отправляет Inertia.js
$data = [
    'performers' => [
        [
            'stage' => 'Плечо 1',
            'contractor_id' => 924,
        ]
    ],
    'financial_term' => [
        'contractors_costs' => [
            [
                'stage' => 'Плечо 1',
                'contractor_id' => 924,
                'amount' => 80000,
                'currency' => 'RUB',
                'payment_form' => 'no_vat',
                'payment_schedule' => [
                    'has_prepayment' => '0',
                    'prepayment_ratio' => '50',
                    'prepayment_days' => '0',
                    'prepayment_mode' => 'fttn',
                    'postpayment_days' => '0',
                    'postpayment_mode' => 'ottn',
                ],
            ]
        ]
    ]
];

echo "Исходные данные:\n";
print_r($data);

echo "\n\nПреобразованные в FormData (как делает Inertia):\n";

// Симулируем преобразование в FormData
function flattenArray($array, $prefix = '') {
    $result = [];
    
    foreach ($array as $key => $value) {
        $newKey = $prefix ? $prefix . '[' . $key . ']' : $key;
        
        if (is_array($value)) {
            $result = array_merge($result, flattenArray($value, $newKey));
        } else {
            $result[$newKey] = $value;
        }
    }
    
    return $result;
}

$flattened = flattenArray($data);
foreach ($flattened as $key => $value) {
    echo "$key: $value\n";
}

// Проверяем, есть ли поле carriers
echo "\n\nПроверяем наличие поля 'carriers':\n";
if (isset($data['carriers'])) {
    echo "Поле 'carriers' присутствует в данных\n";
} else {
    echo "Поле 'carriers' отсутствует в данных\n";
}

// Проверяем, как Inertia обрабатывает данные с forceFormData: true
echo "\n\nПроверяем, как Inertia обрабатывает forceFormData: true\n";
echo "Когда forceFormData: true, Inertia отправляет данные как FormData\n";
echo "FormData автоматически преобразует массивы в формат field[index][key]\n";