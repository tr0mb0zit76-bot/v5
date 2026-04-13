<?php

$url = "http://localhost:8000/finance?section=cashflow";
echo "Testing URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "HTTP Code: $httpCode\n";

if ($response === false) {
    echo "CURL Error: " . curl_error($ch) . "\n";
} else {
    // Пытаемся найти JSON данные в ответе
    if (preg_match('/<div id="app" data-page="([^"]+)"/', $response, $matches)) {
        $json = html_entity_decode($matches[1]);
        $data = json_decode($json, true);
        
        echo "\nFound Inertia data page\n";
        
        if (isset($data['props'])) {
            echo "\nProps keys: " . implode(', ', array_keys($data['props'])) . "\n";
            
            if (isset($data['props']['cashFlowJournal'])) {
                $count = count($data['props']['cashFlowJournal']);
                echo "cashFlowJournal count: $count\n";
                
                if ($count > 0) {
                    echo "\nFirst record keys: " . implode(', ', array_keys($data['props']['cashFlowJournal'][0])) . "\n";
                    
                    // Проверим наличие важных полей
                    $importantFields = ['id', 'order_id', 'order_number', 'amount', 'status', 'direction'];
                    foreach ($importantFields as $field) {
                        $has = isset($data['props']['cashFlowJournal'][0][$field]) ? 'YES' : 'NO';
                        echo "Has $field: $has\n";
                    }
                    
                    // Выведем первые несколько записей для проверки
                    echo "\nFirst 3 records summary:\n";
                    for ($i = 0; $i < min(3, $count); $i++) {
                        $record = $data['props']['cashFlowJournal'][$i];
                        echo "Record $i: ID={$record['id']}, Order={$record['order_number']}, Amount={$record['amount']}, Status={$record['status']}\n";
                    }
                }
            } else {
                echo "ERROR: cashFlowJournal not found in props!\n";
                echo "Available props: " . json_encode(array_keys($data['props'])) . "\n";
            }
        } else {
            echo "ERROR: props not found in data!\n";
            echo "Data structure: " . json_encode(array_keys($data)) . "\n";
        }
    } else {
        echo "Could not find Inertia data in response\n";
        echo "Response preview (first 500 chars):\n" . substr($response, 0, 500) . "\n";
    }
}

curl_close($ch);