<?php

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SalesBookArticle;

foreach ([74, 207, 116, 125] as $parentId) {
    $parent = SalesBookArticle::query()->find($parentId);
    echo '=== '.($parent?->title ?? 'missing')." ({$parentId}) ===\n";
    foreach (SalesBookArticle::query()->where('parent_id', $parentId)->orderBy('id')->get() as $child) {
        echo "  {$child->id}\t{$child->title}\t".($child->status?->value ?? '')."\n";
    }
}
