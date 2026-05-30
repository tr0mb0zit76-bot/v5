<?php

declare(strict_types=1);

use App\Models\Order;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$userId = (int) ($argv[1] ?? 3);
$orderId = (int) ($argv[2] ?? 1);
$httpMethod = strtoupper((string) ($argv[3] ?? 'POST'));

$user = User::query()->find($userId);
if ($user === null) {
    fwrite(STDERR, "User #{$userId} not found.\n");
    exit(1);
}

Auth::login($user);

$order = Order::query()->find($orderId);
if ($order === null) {
    fwrite(STDERR, "Order #{$orderId} not found.\n");
    exit(1);
}

$uri = route('orders.save', $order);

$payload = [
    'status' => $order->status ?? 'new',
    'client_id' => $order->customer_id,
    'order_date' => optional($order->order_date)->format('Y-m-d') ?? date('Y-m-d'),
    'order_number' => $order->order_number,
    'performers' => [
        ['stage' => 'leg_1', 'contractor_id' => $order->carrier_id],
    ],
    'route_points' => [
        [
            'type' => 'loading',
            'sequence' => 1,
            'stage' => 'leg_1',
            'address' => 'Тест загрузки',
            'normalized_data' => [],
        ],
        [
            'type' => 'unloading',
            'sequence' => 2,
            'stage' => 'leg_1',
            'address' => 'Тест выгрузки',
            'normalized_data' => [],
        ],
    ],
    'cargo_items' => [],
    'financial_term' => [
        'client_price' => (float) ($order->customer_rate ?? 1000),
        'client_currency' => 'RUB',
        'client_payment_form' => 'vat',
        'contractors_costs' => [],
    ],
];

/** @var Kernel $kernel */
$kernel = $app->make(Kernel::class);

$session = $app->make('session.store');
if (! $session->isStarted()) {
    $session->start();
}

$payload['_token'] = $session->token();

$request = Request::create($uri, $httpMethod, $payload, [], [], [
    'HTTP_ACCEPT' => 'text/html, application/xhtml+xml',
    'HTTP_X_INERTIA' => 'true',
    'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
    'HTTP_X-CSRF-TOKEN' => $session->token(),
]);
$request->setLaravelSession($session);
$request->setUserResolver(static fn () => $user);

$response = $kernel->handle($request);

echo "{$httpMethod} {$uri}\n";
echo 'Status: '.$response->getStatusCode()."\n";

$location = $response->headers->get('Location');
if ($location !== null) {
    echo "Location: {$location}\n";
}

if ($response->getStatusCode() >= 400) {
    echo "--- body (first 800 chars) ---\n";
    echo substr((string) $response->getContent(), 0, 800)."\n";

    if ($response->getStatusCode() === 404) {
        echo "--- hint: check order exists and user is authenticated ---\n";
    }
}

if ($response->getStatusCode() === 404) {
    try {
        $matched = app('router')->getRoutes()->match($request);
        echo 'Matched route: '.$matched->getName()."\n";
    } catch (Throwable $e) {
        echo 'Route match error: '.$e->getMessage()."\n";
    }
}

$kernel->terminate($request, $response);
