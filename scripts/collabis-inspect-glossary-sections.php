<?php

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SalesBookArticle;

function matchKey(string $title): string
{
    $title = preg_replace('/[\x{E000}-\x{F8FF}\x{FE0F}\x{200D}]/u', '', $title) ?? $title;
    $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title) ?? $title;
    $title = preg_replace('/\s+/u', ' ', trim($title)) ?? trim($title);

    return mb_strtolower($title, 'UTF-8');
}

$needles = [
    'транспортные документы',
    'терминология',
    'финансовые документы',
    'сертификаты и декларации',
    'типы перевозок',
    'виды транспорта',
];

$glossary = SalesBookArticle::query()->whereNull('parent_id')->get()
    ->first(fn ($a) => matchKey($a->title) === matchKey('👨‍🎓Глоссарий'));

echo 'glossary_id='.($glossary?->id ?? 'none').' «'.($glossary?->title ?? '').'»'.PHP_EOL.PHP_EOL;

$all = SalesBookArticle::query()->with('parent')->orderBy('id')->get();

foreach ($needles as $needle) {
    echo "=== {$needle} ===\n";
    foreach ($all as $article) {
        if (matchKey($article->title) === $needle || str_contains(matchKey($article->title), $needle)) {
            $parent = $article->parent?->title ?? 'ROOT';
            $children = SalesBookArticle::query()->where('parent_id', $article->id)->count();
            echo "  {$article->id}\tparent={$parent}\tstatus=".($article->status?->value ?? '')."\tchildren={$children}\t{$article->title}\n";
        }
    }
}

echo PHP_EOL.'=== direct children of glossary ==='.PHP_EOL;
if ($glossary) {
    foreach (SalesBookArticle::query()->where('parent_id', $glossary->id)->orderBy('sort_order')->orderBy('id')->get() as $child) {
        echo "  {$child->id}\t{$child->title}\t".($child->status?->value ?? '').PHP_EOL;
    }
}
