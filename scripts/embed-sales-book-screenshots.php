<?php

declare(strict_types=1);

/**
 * Upload screenshot PNGs into Sales Book assets and patch article markdown.
 * Usage: php scripts/embed-sales-book-screenshots.php [--article-id=10] [--prod]
 */
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Enums\SalesBookArticleStatus;
use App\Models\SalesBookArticle;
use App\Models\User;
use App\Support\SalesBookContentNormalizer;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

$articleId = 10;
$useProd = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--article-id=')) {
        $articleId = (int) substr($arg, strlen('--article-id='));
    }
    if ($arg === '--prod') {
        $useProd = true;
    }
}

$shotsDir = storage_path('app/sales-book-screenshots');
if (! is_dir($shotsDir)) {
    mkdir($shotsDir, 0755, true);
}

/** @var array<string, string> $captions filename => markdown caption */
$planned = [
    '01-documents-registry.png' => 'Реестр документов — вкладка «Все документы».',
    '02-documents-add-modal.png' => 'Добавление документа из реестра.',
    '03-order-documents-tab.png' => 'Вкладка «Документы» в карточке заказа.',
    '04-sales-book-documents.png' => 'Статья «Документы» в Книге продаж.',
];

$user = User::query()->where('email', 'cursor@cursor.ru')->first()
    ?? User::query()->whereHas('role', fn ($q) => $q->where('name', 'admin'))->first();

if ($user === null) {
    fwrite(STDERR, "No user for upload\n");
    exit(1);
}

URL::forceRootUrl($useProd ? 'https://crm.avtoaliyans.ru' : 'http://crm.aa.local');
URL::forceScheme($useProd ? 'https' : 'http');

$article = SalesBookArticle::query()->findOrFail($articleId);
$normalizer = app(SalesBookContentNormalizer::class);
$markdown = (string) ($article->markdown_content ?? '');

$insertBlocks = [];

foreach ($planned as $file => $caption) {
    $path = $shotsDir.DIRECTORY_SEPARATOR.$file;
    if (! is_readable($path)) {
        echo "skip missing: {$file}\n";

        continue;
    }

    $storagePath = 'sales-book-assets/'.uniqid('doc_', true).'-'.$file;
    Storage::disk('local')->put($storagePath, File::get($path));
    $url = route('sales-assistant.book.assets.show', ['path' => $storagePath]);
    $insertBlocks[] = "### {$caption}\n\n![{$caption}]({$url})\n";
    echo "uploaded {$file} -> {$url}\n";
}

if ($insertBlocks === []) {
    fwrite(STDERR, "No screenshots in {$shotsDir}\n");
    exit(1);
}

$marker = '<!-- sales-book-screenshots -->';
$block = $marker."\n\n".implode("\n", $insertBlocks)."\n";

if (str_contains($markdown, $marker)) {
    $markdown = preg_replace('/<!-- sales-book-screenshots -->[\s\S]*/u', rtrim($block), $markdown) ?? $markdown;
} else {
    $markdown = rtrim($markdown)."\n\n".$block;
}

$article->update([
    'markdown_content' => $normalizer->normalize($markdown),
    'status' => SalesBookArticleStatus::Draft->value,
    'updated_by' => $user->id,
]);

echo "OK article {$article->id} updated, status=draft\n";
echo route('sales-assistant.book', ['article_id' => $article->id])."\n";
