<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Order;
use App\Services\OrderWizardService;

echo "Testing form submission for order OOOA-2604-0006...\n";
echo "====================================================\n\n";

$order = Order::where('order_number', 'OOOA-2604-0006')->first();

if (!$order) {
    echo "Order not found\n";
    exit(1);
}

echo "1. Current state before update:\n";
echo "   Order ID: " . $order->id . "\n";
echo "   Carrier ID: " . $order->carrier_id . "\n";
echo "   Performers: " . json_encode($order->performers, JSON_PRETTY_PRINT) . "\n";

// Simulate form data when user deletes carrier
$formData = [
    'order_date' => $order->order_date->toDateString(),
    'client_id' => $order->customer_id,
    'own_company_id' => $order->own_company_id,
    'status' => $order->status,
    'special_notes' => $order->special_notes,
    'performers' => [
        [
            'stage' => 'Плечо 1',
            'contractor_id' => null, // User deleted the carrier
        ]
    ],
    'financial_term' => [
        'client_price' => $order->customer_rate,
        'client_currency' => 'RUB',
        'client_payment_form' => $order->customer_payment_form,
        'client_request_mode' => 'single_request',
        'client_payment_schedule' => [
            'has_prepayment' => '0',
            'prepayment_days' => '0',
            'prepayment_mode' => 'fttn',
            'postpayment_days' => '7',
            'postpayment_mode' => 'ottn',
            'prepayment_ratio' => '50'
        ],
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
        ],
        'additional_costs' => [],
    ],
    'route_points' => [],
    'cargo_items' => [],
    'documents' => [],
];

echo "\n2. Simulating update with deleted carrier...\n";
echo "   Form data performers: " . json_encode($formData['performers'], JSON_PRETTY_PRINT) . "\n";

// Create a mock user
$user = $order->manager;

// Update the order
$service = app(OrderWizardService::class);
$updatedOrder = $service->update($order, $formData, $user);

echo "\n3. State after update:\n";
echo "   Carrier ID: " . ($updatedOrder->carrier_id ?? 'null') . "\n";
echo "   Performers: " . json_encode($updatedOrder->performers, JSON_PRETTY_PRINT) . "\n";

// Reload from database to confirm
$reloadedOrder = Order::find($order->id);
echo "\n4. Reloaded from database:\n";
echo "   Carrier ID: " . ($reloadedOrder->carrier_id ?? 'null') . "\n";
echo "   Performers: " . json_encode($reloadedOrder->performers, JSON_PRETTY_PRINT) . "\n";

if ($reloadedOrder->carrier_id === null) {
    echo "\n✓ SUCCESS: Carrier ID was successfully set to null\n";
} else {
    echo "\n✗ FAILURE: Carrier ID is still " . $reloadedOrder->carrier_id . "\n";
    
    // Check if there's a model observer or event
    echo "\n5. Checking for model events/observers...\n";
    
    // Check if Order model has any observers
    $observers = [];
    if (method_exists($order, 'getObservableEvents')) {
        $events = $order->getObservableEvents();
        foreach ($events as $event) {
            if (method_exists($order, 'getEventDispatcher') && $order->getEventDispatcher()->hasListeners("eloquent.{$event}: " . get_class($order))) {
                $observers[] = $event;
            }
        }
    }
    
    if (!empty($observers)) {
        echo "   Found observers for events: " . implode(', ', $observers) . "\n";
    } else {
        echo "   No observers found\n";
    }
    
    // Check for model traits
    echo "   Model traits: ";
    $traits = class_uses($order);
    if ($traits) {
        echo implode(', ', $traits) . "\n";
    } else {
        echo "none\n";
    }
}

echo "\n====================================================\n";