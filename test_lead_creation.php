<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\LeadController;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

echo "Testing lead creation from manager perspective...\n\n";

// First, let's check the current user
$user = User::find(3);
if (! $user) {
    echo "User with ID 3 not found\n";
    exit(1);
}

echo "User found:\n";
echo '- ID: '.$user->id."\n";
echo '- Name: '.$user->name."\n";
echo '- Email: '.$user->email."\n";

// Check user methods
echo "\nChecking user methods:\n";
echo '- isAdmin(): '.($user->isAdmin() ? 'Yes' : 'No')."\n";
echo '- isManager(): '.($user->isManager() ? 'Yes' : 'No')."\n";
echo '- isSupervisor(): '.($user->isSupervisor() ? 'Yes' : 'No')."\n";

// Check role
if ($user->role) {
    echo '- Role: '.$user->role->name."\n";
    echo '- Visibility scopes: '.json_encode($user->role->visibility_scopes ?? [])."\n";
} else {
    echo "- Role: None\n";
}

// Now let's simulate a lead creation request
echo "\nSimulating lead creation request...\n";

// Create a mock request
$requestData = [
    'status' => 'new',
    'source' => 'inbound',
    'counterparty_id' => 1, // Бетондеталь
    'responsible_id' => 3, // Ярослав Щеглов (current user)
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
            'planned_date' => '2026-04-10',
            'contact_person' => 'Иван Иванов',
            'contact_phone' => '+79991234567',
        ],
        [
            'type' => 'unloading',
            'sequence' => 2,
            'address' => 'Санкт-Петербург, ул. Пушкина, 2',
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

// Create request instance
$request = Request::create('/leads', 'POST', $requestData);
$request->setUserResolver(function () use ($user) {
    return $user;
});

// Validate the request
echo "\nValidating request data...\n";
$storeLeadRequest = new StoreLeadRequest;
$storeLeadRequest->setContainer(app());
$storeLeadRequest->initialize(
    $requestData,
    $requestData,
    [],
    [],
    [],
    $request->server->all(),
    $request->getContent()
);
$storeLeadRequest->setUserResolver(function () use ($user) {
    return $user;
});

try {
    $validated = $storeLeadRequest->validated();
    echo "✓ Validation passed\n";

    // Check authorization
    $authorized = $storeLeadRequest->authorize();
    echo '✓ Authorization: '.($authorized ? 'Granted' : 'Denied')."\n";

    // Now test the controller
    echo "\nTesting controller store method...\n";

    $controller = new LeadController;

    // Check if hasLeadsFeatureTables passes
    $reflection = new ReflectionClass($controller);
    $hasTablesMethod = $reflection->getMethod('hasLeadsFeatureTables');
    $hasTablesMethod->setAccessible(true);
    $hasTables = $hasTablesMethod->invoke($controller);

    echo '- hasLeadsFeatureTables: '.($hasTables ? 'true' : 'false')."\n";

    if (! $hasTables) {
        echo "✗ Lead tables not available\n";
        exit(1);
    }

    // Try to create lead
    echo "\nAttempting to create lead...\n";

    // We need to mock the request properly
    $storeLeadRequest->merge($requestData);

    // Use DB transaction to test creation
    DB::beginTransaction();

    try {
        $lead = Lead::create([
            'number' => 'LD-260406-001',
            'status' => $validated['status'],
            'source' => $validated['source'],
            'counterparty_id' => $validated['counterparty_id'],
            'responsible_id' => $validated['responsible_id'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'transport_type' => $validated['transport_type'],
            'loading_location' => $validated['loading_location'],
            'unloading_location' => $validated['unloading_location'],
            'planned_shipping_date' => $validated['planned_shipping_date'],
            'target_price' => $validated['target_price'],
            'target_currency' => $validated['target_currency'],
            'calculated_cost' => $validated['calculated_cost'],
            'expected_margin' => $validated['expected_margin'],
            'next_contact_at' => $validated['next_contact_at'],
            'lead_qualification' => $validated['qualification'],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        echo "✓ Lead created successfully!\n";
        echo '- Lead ID: '.$lead->id."\n";
        echo '- Lead Number: '.$lead->number."\n";
        echo '- Title: '.$lead->title."\n";

        // Test nested data sync
        echo "\nTesting nested data sync...\n";

        $syncMethod = $reflection->getMethod('syncNestedData');
        $syncMethod->setAccessible(true);
        $syncMethod->invoke($controller, $lead, $request);

        echo "✓ Nested data synced\n";

        // Check what was created
        echo "\nCreated records:\n";
        echo '- Route points: '.$lead->routePoints()->count()."\n";
        echo '- Cargo items: '.$lead->cargoItems()->count()."\n";
        echo '- Activities: '.$lead->activities()->count()."\n";

    } catch (Exception $e) {
        echo '✗ Error creating lead: '.$e->getMessage()."\n";
        echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
        echo 'Trace: '.$e->getTraceAsString()."\n";
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
