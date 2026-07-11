<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (App\Models\SalesBookArticle::query()->with('parent')->orderBy('id')->get() as $article) {
    $parentTitle = $article->parent?->title ?? '';
    echo $parentTitle."\t".$article->title."\n";
}
