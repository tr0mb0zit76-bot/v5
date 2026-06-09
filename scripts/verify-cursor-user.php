<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$ok = Auth::attempt([
    'email' => 'cursor@cursor.ru',
    'password' => '4xS-kNB-cwu-V9Y',
]);

echo $ok ? "auth-ok\n" : "auth-fail\n";
echo 'db='.config('database.connections.mysql.database')."\n";
