<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Services\OrderWizardService;

echo "Testing carrier deletion for order OOOA-2604-0006\n";
echo "================================================\n\n";

$order = Order::where('order_number', 'OOOA-2604-0006')->first();

if (!$order) {
    echo "Order not found\n";
    exit(1);
}

echo "1. Current state:\n";
echo "   Order ID: " . $order->id . "\n";
echo "   Carrier ID: " . $order->carrier_id . "\n";
echo "   Performers: " . json_encode($order->performers, JSON_PRETTY_PRINT) . "\n";

// Simulate what happens when user deletes carrier
$simulatedData = [
    'performers' => [
        [
            'stage' => 'Плечо 1',
            'contractor_id' => null, // User deleted the carrier
        ]
    ],
    'financial_term' => [
        'contractors_costs' => [
            [
                'stage' => 'Плечо 1',
                'contractor_id' => null, // Should also be null
                'amount' => '250000',
                'currency' => 'RUB',
                'payment_form' => 'vat',
                'payment_schedule' => [
                    'has_prepayment' => '0',
                    'prepayment_days' => '0',
                    'prepayment_mode' => 'fttn',
                    'postpayment_days' => '7',
                    'postpayment_mode' => 'ottn',
                    'prepayment_ratio' => '50'
                ]
            ]
        ]
    ]
];

echo "\n2. Simulating deletion of carrier (setting contractor_id to null)...\n";

// Test the logic from extractOrderAttributes
$normalizedPerformers = collect($simulatedData['performers'])
    ->map(function (array $performer): array {
        if (isset($performer['contractor_id']) && $performer['contractor_id'] !== null) {
            $performer['contractor_id'] = (int) $performer['contractor_id'];
        }
        return $performer;
    })
    ->all();

echo "   Normalized performers: " . json_encode($normalizedPerformers, JSON_PRETTY_PRINT) . "\n";

$carrierId = collect($normalizedPerformers)->pluck('contractor_id')->filter()->first();
$carrierId = $carrierId !== null ? (int) $carrierId : null;

echo "   Calculated carrier_id: " . ($carrierId ?? 'null') . "\n";

if ($carrierId === null) {
    echo "   ✓ Carrier ID would be set to null\n";
} else {
    echo "   ✗ Carrier ID would NOT be set to null (it would be: {$carrierId})\n";
}

echo "\n3. Checking OrderWizardService logic...\n";

// Check if the service would update carrier_id correctly
$service = app(OrderWizardService::class);

// We need to reflect to access private method
$reflection = new ReflectionClass($service);
$method = $reflection->getMethod('extractOrderAttributes');
$method->setAccessible(true);

// Create minimal data for the method
$user = $order->manager; // Get the manager
$numberData = ['company_code' => 'OOOA', 'order_number' => 'OOOA-2604-0006'];

$attributes = $method->invoke($service, array_merge($simulatedData, [
    'order_date' => $order->order_date,
    'client_id' => $order->customer_id,
    'own_company_id' => $order->own_company_id,
    'status' => $order->status,
    'special_notes' => $order->special_notes,
]), $user, $numberData, false);

echo "   Extracted carrier_id attribute: " . ($attributes['carrier_id'] ?? 'null') . "\n";

if (($attributes['carrier_id'] ?? null) === null) {
    echo "   ✓ OrderWizardService would set carrier_id to null\n";
} else {
    echo "   ✗ OrderWizardService would NOT set carrier_id to null\n";
}

echo "\n4. Checking if the issue might be elsewhere...\n";
echo "   Possible issues:\n";
echo "   - Table view might be caching old data\n";
echo "   - There might be a default value for carrier_id\n";
echo "   - The update might not be saving correctly\n";
echo "   - There might be a trigger or event that restores old value\n";

echo "\n================================================\n";
echo "Conclusion: The logic for setting carrier_id to null seems correct.\n";
echo "The issue might be in how the table displays data or in some other part of the system.\n";