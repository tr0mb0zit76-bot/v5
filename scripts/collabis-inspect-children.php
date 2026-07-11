<?php

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SalesBookArticle;

foreach ([33, 38, 118, 122] as $parentId) {
    echo "=== parent {$parentId} ===\n";
    foreach (SalesBookArticle::query()->where('parent_id', $parentId)->orderBy('sort_order')->orderBy('id')->get() as $child) {
        echo "  {$child->id}\t{$child->title}\t".($child->status?->value ?? '')."\n";
    }
}
