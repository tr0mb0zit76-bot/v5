<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "Testing lead creation payload...\n\n";

// Simulate the exact payload that would come from the Vue form
$payload = [
    'status' => 'new',
    'source' => 'inbound',
    'counterparty_id' => 1,
    'responsible_id' => 3,
    'title' => 'Тестовая заявка от менеджера',
    'description' => 'Тестовое описание заявки',
    'transport_type' => 'ftl',
    'loading_location' => 'Москва',
    'unloading_location' => 'Санкт-Петербург',
    'planned_shipping_date' => '2026-04-10',
    'target_price' => 100000,
    'target_currency' => 'RUB',
    'calculated_cost' => 80000,
    'expected_margin' => 20000,
    'next_contact_at' => '2026-04-07T10:00',
    'qualification' => [
        'need' => 'Срочная доставка',
        'timeline' => 'Срочно',
        'authority' => 'Директор',
        'budget' => '100000 RUB',
    ],
    'route_points' => [
        [
            'type' => 'loading',
            'sequence' => 1,
            'address' => 'Москва, ул. Ленина, 1',
            'normalized_data' => [],
            'planned_date' => '2026-04-10',
            'contact_person' => 'Иван Иванов',
            'contact_phone' => '+79991234567',
        ],
        [
            'type' => 'unloading',
            'sequence' => 2,
            'address' => 'Санкт-Петербург, ул. Пушкина, 2',
            'normalized_data' => [],
            'planned_date' => '2026-04-11',
            'contact_person' => 'Петр Петров',
            'contact_phone' => '+79997654321',
        ],
    ],
    'cargo_items' => [
        [
            'name' => 'Бетонные блоки',
            'description' => 'Строительные материалы',
            'weight_kg' => 5000,
            'volume_m3' => 10,
            'package_type' => 'pallet',
            'package_count' => 20,
            'dangerous_goods' => false,
            'dangerous_class' => '',
            'hs_code' => '',
            'cargo_type' => 'general',
        ],
    ],
    'activities' => [
        [
            'type' => 'call',
            'subject' => 'Первичный звонок',
            'content' => 'Обсудили детали заявки',
            'next_action_at' => '2026-04-07T14:00',
        ],
    ],
];

echo "Payload structure:\n";
echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n";

// Now test validation
echo "Testing validation...\n";

use App\Http\Controllers\LeadController;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Contractor;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$request = Request::create('/leads', 'POST', $payload);
$user = User::find(3);
$request->setUserResolver(function () use ($user) {
    return $user;
});

$storeLeadRequest = StoreLeadRequest::createFrom($request);
$storeLeadRequest->setContainer(app());
$storeLeadRequest->setRedirector(app('redirect'));
$storeLeadRequest->setUserResolver(function () use ($user) {
    return $user;
});

try {
    // First validate the request
    $storeLeadRequest->validateResolved();

    // Now we can get validated data
    $validated = $storeLeadRequest->validated();
    echo "✓ Validation passed\n\n";

    echo "Validated data (first few fields):\n";
    foreach (array_slice($validated, 0, 5) as $key => $value) {
        if (is_array($value)) {
            echo "  - $key: [array]\n";
        } else {
            echo "  - $key: ".(string) $value."\n";
        }
    }
    echo "  ... (truncated)\n";

    // Now test controller
    echo "\n\nTesting controller store method...\n";

    $controller = new LeadController;

    // Use reflection to test private method
    $reflection = new ReflectionClass($controller);
    $hasTablesMethod = $reflection->getMethod('hasLeadsFeatureTables');
    $hasTablesMethod->setAccessible(true);

    if (! $hasTablesMethod->invoke($controller)) {
        echo "✗ Lead tables not available\n";
        exit(1);
    }

    echo "✓ Lead tables available\n";

    // Test authorization
    $authorized = $storeLeadRequest->authorize();
    echo '✓ Authorization: '.($authorized ? 'Granted' : 'Denied')."\n";

    if (! $authorized) {
        echo "✗ User not authorized to create leads\n";
        exit(1);
    }

    // Test actual creation
    echo "\nTesting actual lead creation...\n";

    DB::beginTransaction();

    try {
        // Call the store method
        $storeMethod = $reflection->getMethod('store');
        $response = $storeMethod->invoke($controller, $storeLeadRequest);

        echo "✓ Store method executed successfully\n";
        echo 'Response type: '.get_class($response)."\n";

    } catch (Exception $e) {
        echo '✗ Error in store method: '.$e->getMessage()."\n";
        echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
        if ($e instanceof ValidationException) {
            echo "Validation errors:\n";
            print_r($e->errors());
        }
    }

    DB::rollBack();
    echo "\n✓ Transaction rolled back (test only)\n";

} catch (ValidationException $e) {
    echo "✗ Validation failed:\n";
    foreach ($e->errors() as $field => $errors) {
        echo "  - $field: ".implode(', ', $errors)."\n";
    }
} catch (Exception $e) {
    echo '✗ Error: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
}

// Check for common issues
echo "\n\nChecking for common issues:\n";

// 1. Check if responsible_id exists in users table
$responsibleExists = User::where('id', $payload['responsible_id'])->exists();
echo "1. responsible_id {$payload['responsible_id']} exists in users: ".($responsibleExists ? 'Yes' : 'No')."\n";

// 2. Check if counterparty_id exists in contractors table
$counterpartyExists = Contractor::where('id', $payload['counterparty_id'])->exists();
echo "2. counterparty_id {$payload['counterparty_id']} exists in contractors: ".($counterpartyExists ? 'Yes' : 'No')."\n";

// 3. Check date formats
echo "3. Date format check:\n";
echo '   - planned_shipping_date: '.$payload['planned_shipping_date'].' (valid date: '.(strtotime($payload['planned_shipping_date']) ? 'Yes' : 'No').")\n";
echo '   - next_contact_at: '.$payload['next_contact_at'].' (valid datetime: '.(strtotime($payload['next_contact_at']) ? 'Yes' : 'No').")\n";

// 4. Check boolean conversion
echo "4. Boolean field check:\n";
echo '   - dangerous_goods: '.($payload['cargo_items'][0]['dangerous_goods'] ? 'true' : 'false').' (type: '.gettype($payload['cargo_items'][0]['dangerous_goods']).")\n";

echo "\nTest complete.\n";
