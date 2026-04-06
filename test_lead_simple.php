<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\LeadController;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

echo "Testing lead creation - simple approach...\n\n";

// Check user
$user = User::find(3);
if (! $user) {
    echo "User not found\n";
    exit(1);
}

echo "User: {$user->name} (ID: {$user->id}, Role: ".($user->role?->name ?? 'none').")\n";

// Check if we can access the lead creation page
echo "\nChecking lead creation access...\n";

$controller = new LeadController;
$request = Request::create('/leads/create', 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

try {
    // Test the create method
    $response = $controller->create($request);
    echo "✓ Create page accessible\n";
} catch (Exception $e) {
    echo '✗ Error accessing create page: '.$e->getMessage()."\n";
}

// Now test actual lead creation
echo "\nTesting direct lead creation in database...\n";

DB::beginTransaction();

try {
    // First, check next lead number
    $prefix = 'LD-'.now()->format('ymd');
    $sequence = DB::table('leads')
        ->where('number', 'like', $prefix.'-%')
        ->count() + 1;
    $number = sprintf('%s-%03d', $prefix, $sequence);

    echo "Next lead number: {$number}\n";

    // Create lead directly
    $leadData = [
        'number' => $number,
        'status' => 'new',
        'source' => 'inbound',
        'counterparty_id' => 1,
        'responsible_id' => $user->id,
        'title' => 'Тестовая заявка',
        'description' => 'Тестовое описание',
        'transport_type' => 'ftl',
        'loading_location' => 'Москва',
        'unloading_location' => 'Санкт-Петербург',
        'planned_shipping_date' => '2026-04-10',
        'target_price' => 100000,
        'target_currency' => 'RUB',
        'calculated_cost' => 80000,
        'expected_margin' => 20000,
        'next_contact_at' => '2026-04-07 10:00:00',
        'lead_qualification' => json_encode(['need' => 'Срочная доставка']),
        'created_by' => $user->id,
        'updated_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $leadId = DB::table('leads')->insertGetId($leadData);
    echo "✓ Lead created with ID: {$leadId}\n";

    // Check if we can retrieve it
    $lead = DB::table('leads')->find($leadId);
    echo "✓ Lead retrieved: {$lead->title}\n";

    // Test route points
    $routePointData = [
        'lead_id' => $leadId,
        'type' => 'loading',
        'sequence' => 1,
        'address' => 'Москва, ул. Ленина, 1',
        'planned_date' => '2026-04-10',
        'contact_person' => 'Иван Иванов',
        'contact_phone' => '+79991234567',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $routePointId = DB::table('lead_route_points')->insertGetId($routePointData);
    echo "✓ Route point created with ID: {$routePointId}\n";

    // Test cargo items
    $cargoData = [
        'lead_id' => $leadId,
        'name' => 'Бетонные блоки',
        'description' => 'Строительные материалы',
        'weight_kg' => 5000,
        'volume_m3' => 10,
        'package_type' => 'pallet',
        'package_count' => 20,
        'dangerous_goods' => 0,
        'cargo_type' => 'general',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $cargoId = DB::table('lead_cargo_items')->insertGetId($cargoData);
    echo "✓ Cargo item created with ID: {$cargoId}\n";

    // Test activities
    $activityData = [
        'lead_id' => $leadId,
        'type' => 'call',
        'subject' => 'Первичный звонок',
        'content' => 'Обсудили детали заявки',
        'next_action_at' => '2026-04-07 14:00:00',
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    $activityId = DB::table('lead_activities')->insertGetId($activityData);
    echo "✓ Activity created with ID: {$activityId}\n";

    echo "\n✓ All database operations successful!\n";

} catch (Exception $e) {
    echo '✗ Error: '.$e->getMessage()."\n";
    echo 'File: '.$e->getFile().':'.$e->getLine()."\n";
    echo 'Trace: '.$e->getTraceAsString()."\n";
}

DB::rollBack();
echo "\n✓ Transaction rolled back (test only)\n";

// Now let's check what the actual issue might be
echo "\n\nChecking potential issues...\n";

// 1. Check if the user can access the lead creation form
echo "\n1. Checking user permissions for lead creation:\n";
echo "- User ID: {$user->id}\n";
echo '- User role: '.($user->role?->name ?? 'none')."\n";

// 2. Check the actual controller store method requirements
echo "\n2. Controller store method requirements:\n";
echo '- hasLeadsFeatureTables(): '.(Schema::hasTable('leads') && Schema::hasTable('lead_route_points') &&
    Schema::hasTable('lead_cargo_items') && Schema::hasTable('lead_activities') &&
    Schema::hasTable('lead_offers') ? 'true' : 'false')."\n";

// 3. Check validation rules
echo "\n3. Key validation rules for lead creation:\n";
echo "- status: required, must be in list\n";
echo "- responsible_id: required, must exist in users table\n";
echo "- title: required, max 255 chars\n";
echo "- target_currency: required_with:target_price\n";

// 4. Check if there are any middleware or policies blocking access
echo "\n4. Checking route middleware...\n";
exec('php artisan route:list --method=POST --name=leads.store --verbose', $output);
if (! empty($output)) {
    foreach ($output as $line) {
        if (strpos($line, 'leads.store') !== false) {
            echo "Route: {$line}\n";
        }
    }
}

echo "\nTest complete.\n";
