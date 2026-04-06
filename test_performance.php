<?php

use App\Models\Contractor;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

echo "Testing performance...\n";

// Enable query log
DB::enableQueryLog();

// Test 1: Count contractors
$count = Contractor::where('is_active', true)->orWhere('is_own_company', true)->count();
echo "Active/own contractors: $count\n";

// Test 2: Load contractors with minimal columns
$contractors = Contractor::query()
    ->where(function ($query) {
        $query->where('is_active', true)
            ->orWhere('is_own_company', true);
    })
    ->orderByDesc('is_own_company')
    ->orderBy('name')
    ->get(['id', 'name', 'is_active', 'is_own_company', 'debt_limit', 'stop_on_limit']);

echo "Loaded {$contractors->count()} contractors\n";

// Test 3: Check debt calculation (simulate credit service)
$debtContractorIds = $contractors
    ->filter(fn ($contractor) => ($contractor->stop_on_limit ?? false) && $contractor->debt_limit !== null)
    ->pluck('id')
    ->all();

echo 'Contractors with debt limit: '.count($debtContractorIds)."\n";

$queries = DB::getQueryLog();
echo "\nTotal queries: ".count($queries)."\n";
echo 'Total time: '.array_sum(array_column($queries, 'time'))."ms\n";

foreach ($queries as $i => $query) {
    echo "\nQuery {$i}:\n";
    echo 'SQL: '.$query['query']."\n";
    echo 'Time: '.($query['time'] ?? 'N/A')."ms\n";
    if (! empty($query['bindings'])) {
        echo 'Bindings: '.json_encode($query['bindings'])."\n";
    }
}
