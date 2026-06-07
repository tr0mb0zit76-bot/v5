<?php

declare(strict_types=1);

/**
 * Find Sales Book articles for documents guide.
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\SalesBookArticle;
use Illuminate\Contracts\Console\Kernel;

$articles = SalesBookArticle::query()
    ->whereIn('title', ['Документы', 'Регламент работы с документами'])
    ->orWhere('title', 'like', 'Руководство%CRM%')
    ->orWhere('title', 'Регламенты работы')
    ->get(['id', 'title', 'parent_id', 'status']);

foreach ($articles as $article) {
    echo "{$article->id}\t{$article->status->value}\t{$article->title}\n";
}
