<?php

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SalesBookArticle;

$ids = [33, 38, 118, 122, 169, 186];

foreach ($ids as $id) {
    $article = SalesBookArticle::query()->with('parent')->find($id);
    if ($article === null) {
        echo "MISSING id={$id}\n";
        continue;
    }
    $parent = $article->parent?->title ?? 'ROOT';
    $children = SalesBookArticle::query()->where('parent_id', $id)->count();
    echo "{$id}\tparent={$parent}\tstatus=".($article->status?->value ?? '')."\tchildren={$children}\t{$article->title}\n";
}

$express = SalesBookArticle::query()->whereNull('parent_id')->get()
    ->first(fn ($a) => str_contains(mb_strtolower($a->title), 'экспресс'));
echo 'express_id='.($express?->id ?? 'none')."\t".($express?->title ?? '')."\n";
