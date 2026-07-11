<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;

$role = Role::query()->where('name', 'admin')->first();

if ($role === null) {
    fwrite(STDERR, "Admin role not found\n");
    exit(1);
}

$user = User::query()->updateOrCreate(
    ['email' => 'cursor@cursor.ru'],
    [
        'name' => 'Cursor Agent',
        'password' => Hash::make(getenv('CURSOR_USER_PASSWORD') ?: 'cursor'),
        'role_id' => $role->id,
        'email_verified_at' => now(),
    ],
);

echo "OK user_id={$user->id} email={$user->email}\n";
