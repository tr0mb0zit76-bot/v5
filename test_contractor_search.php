<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Create a test user and authenticate
$user = \App\Models\User::first();
if (!$user) {
    echo "No users found in database\n";
    exit(1);
}

// Create a request with authentication
$request = Illuminate\Http\Request::create('/contractors-search?q=test&type=customer&limit=10', 'GET');
$request->setUserResolver(function () use ($user) {
    return $user;
});

$response = $kernel->handle($request);

echo "Status: " . $response->getStatusCode() . "\n";
echo "Content: " . $response->getContent() . "\n";