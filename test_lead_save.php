<?php

use App\Models\Contractor;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "Testing lead save functionality...\n";

// Check if lead tables exist
$tables = ['leads', 'lead_route_points', 'lead_cargo_items', 'lead_activities', 'lead_offers'];
foreach ($tables as $table) {
    $exists = Schema::hasTable($table);
    echo "Table $table exists: ".($exists ? 'YES' : 'NO')."\n";
}

// Check a sample user
$user = User::first();
echo 'Sample user ID: '.($user ? $user->id : 'NO USERS')."\n";

// Check contractors
$contractor = Contractor::first();
echo 'Sample contractor ID: '.($contractor ? $contractor->id : 'NO CONTRACTORS')."\n";

// Test validation rules
$requestData = [
    'status' => 'new',
    'responsible_id' => $user ? $user->id : 1,
    'title' => 'Test Lead',
    'target_currency' => 'RUB',
];

try {
    $validator = Validator::make($requestData, [
        'status' => ['required', Rule::in(['new', 'qualification', 'calculation', 'proposal_ready', 'proposal_sent', 'negotiation', 'won', 'lost', 'on_hold'])],
        'source' => ['nullable', 'string', 'max:100'],
        'counterparty_id' => ['nullable', 'integer', 'exists:contractors,id'],
        'responsible_id' => ['required', 'integer', 'exists:users,id'],
        'title' => ['required', 'string', 'max:255'],
        'description' => ['nullable', 'string'],
        'transport_type' => ['nullable', 'string', 'max:100'],
        'loading_location' => ['nullable', 'string', 'max:255'],
        'unloading_location' => ['nullable', 'string', 'max:255'],
        'planned_shipping_date' => ['nullable', 'date'],
        'target_price' => ['nullable', 'numeric', 'min:0'],
        'target_currency' => ['required_with:target_price', Rule::in(['RUB', 'USD', 'CNY', 'EUR'])],
        'calculated_cost' => ['nullable', 'numeric', 'min:0'],
        'expected_margin' => ['nullable', 'numeric'],
        'next_contact_at' => ['nullable', 'date'],
        'lost_reason' => ['nullable', 'string', 'max:255'],
    ]);

    if ($validator->fails()) {
        echo "Validation errors:\n";
        foreach ($validator->errors()->all() as $error) {
            echo " - $error\n";
        }
    } else {
        echo "Validation passed\n";
    }
} catch (Exception $e) {
    echo 'Validation error: '.$e->getMessage()."\n";
}

// Check nextLeadNumber logic
echo "\nTesting nextLeadNumber logic:\n";
try {
    $prefix = 'LD-'.now()->format('ymd');
    $sequence = DB::table('leads')
        ->where('number', 'like', $prefix.'-%')
        ->count() + 1;
    $nextNumber = sprintf('%s-%03d', $prefix, $sequence);
    echo "Next lead number would be: $nextNumber\n";
} catch (Exception $e) {
    echo 'Error generating lead number: '.$e->getMessage()."\n";
}
