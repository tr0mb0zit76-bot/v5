<?php

// Тест логики синхронизации

$performers = [
    ['stage' => 'leg_1', 'contractor_id' => 123], // Старый исполнитель
];

$contractorsCosts = [
    ['stage' => 'leg_1', 'contractor_id' => 456, 'amount' => 1000], // Новый исполнитель в contractors_costs
];

echo "До синхронизации:\n";
echo "performers[0].contractor_id = " . $performers[0]['contractor_id'] . "\n";
echo "contractorsCosts[0].contractor_id = " . $contractorsCosts[0]['contractor_id'] . "\n\n";

// Логика syncContractorsCostsWithPerformers
$performersByStage = [];
foreach ($performers as $performer) {
    $performersByStage[$performer['stage'] ?? 'leg_1'] = $performer;
}

foreach ($contractorsCosts as &$cost) {
    $stage = $cost['stage'] ?? 'leg_1';
    $performer = $performersByStage[$stage] ?? null;
    
    if ($performer && array_key_exists('contractor_id', $performer)) {
        $cost['contractor_id'] = $performer['contractor_id'] !== null 
            ? (int) $performer['contractor_id'] 
            : null;
    }
}

echo "После синхронизации:\n";
echo "performers[0].contractor_id = " . $performers[0]['contractor_id'] . "\n";
echo "contractorsCosts[0].contractor_id = " . $contractorsCosts[0]['contractor_id'] . "\n\n";

echo "Вывод: contractors_costs перезаписывается данными из performers!\n";
echo "Если performers содержит старые данные, то contractors_costs тоже получит старые данные.\n";