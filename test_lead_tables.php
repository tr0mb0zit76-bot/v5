<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Http\Controllers\LeadController;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

echo "Checking lead tables...\n\n";

$tables = [
    'leads',
    'lead_route_points',
    'lead_cargo_items',
    'lead_activities',
    'lead_offers',
];

foreach ($tables as $table) {
    $exists = Schema::hasTable($table);
    echo $table.': '.($exists ? 'EXISTS' : 'MISSING')."\n";
}

echo "\nChecking hasLeadsFeatureTables() method...\n";
$controller = new LeadController;
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('hasLeadsFeatureTables');
$method->setAccessible(true);
$result = $method->invoke($controller);

echo 'hasLeadsFeatureTables() returns: '.($result ? 'true' : 'false')."\n";

// Check if we can create a lead
echo "\nTesting lead creation...\n";
try {
    $lead = Lead::first();
    if ($lead) {
        echo 'Found existing lead with ID: '.$lead->id."\n";
    } else {
        echo "No leads found in database\n";
    }
} catch (Exception $e) {
    echo 'Error accessing leads table: '.$e->getMessage()."\n";
}

// Check users table for responsible_id
echo "\nChecking users table...\n";
$user = User::first();
if ($user) {
    echo 'Found user with ID: '.$user->id.', Name: '.$user->name."\n";
} else {
    echo "No users found\n";
}

// Check contractors table for counterparty_id
echo "\nChecking contractors table...\n";
$contractor = Contractor::first();
if ($contractor) {
    echo 'Found contractor with ID: '.$contractor->id.', Name: '.$contractor->name."\n";
} else {
    echo "No contractors found\n";
}
