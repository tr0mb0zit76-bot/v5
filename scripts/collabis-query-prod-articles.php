<?php

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SalesBookArticle;

$patterns = ['%Ускоряемся%', '%Профи%', '%ускоряемся%', '%профи%'];

$articles = SalesBookArticle::query()
    ->with('parent')
    ->where(function ($q) use ($patterns) {
        foreach ($patterns as $pattern) {
            $q->orWhere('title', 'like', $pattern);
        }
    })
    ->orderBy('id')
    ->get();

foreach ($articles as $article) {
    $parent = $article->parent?->title ?? 'ROOT';
    echo $article->id."\t".$parent."\t".$article->title."\t".($article->status?->value ?? '')."\n";
}

echo 'total='.$articles->count().PHP_EOL;
