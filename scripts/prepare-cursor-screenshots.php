<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\SalesBookArticle;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

$adminRole = Role::query()->where('name', 'admin')->first();
if ($adminRole === null) {
    fwrite(STDERR, "admin role missing\n");
    exit(1);
}

$user = User::query()->updateOrCreate(
    ['email' => 'cursor@cursor.ru'],
    [
        'name' => 'Cursor Agent',
        'role_id' => $adminRole->id,
        'email_verified_at' => now(),
    ],
);

echo "user_id={$user->id} role=admin\n";

$articles = SalesBookArticle::query()
    ->whereIn('title', ['Документы', 'Регламент работы с документами', 'Руководство по CRM', 'Регламенты работы'])
    ->get(['id', 'title', 'status']);

foreach ($articles as $article) {
    $status = $article->status instanceof BackedEnum ? $article->status->value : (string) $article->status;
    echo "article {$article->id} [{$status}] {$article->title}\n";
}
