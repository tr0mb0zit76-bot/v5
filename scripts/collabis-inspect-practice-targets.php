<?php

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: dirname(__DIR__);
require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SalesBookArticle;

function matchKey(string $t): string
{
    $t = preg_replace('/[\x{E000}-\x{F8FF}\x{FE0F}\x{200D}]/u', '', $t) ?? $t;
    $t = preg_replace('/[^\p{L}\p{N}\s]/u', '', $t) ?? $t;

    return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $t) ?? $t), 'UTF-8');
}

$needles = [
    'алгоритм воронки',
    'сбор информации о клиенте',
    'техники закрытия',
    'топ-5 возражений',
    'день 4',
    'день 5',
    'день 7',
    'день 9',
    'регламенты',
    'воронка продаж',
    'ускоряемся',
];

$all = SalesBookArticle::query()->with('parent')->orderBy('id')->get();
foreach ($needles as $needle) {
    echo "=== {$needle} ===\n";
    foreach ($all as $a) {
        if (str_contains(matchKey($a->title), $needle) || ($a->parent && str_contains(matchKey($a->parent->title), $needle) && str_contains(matchKey($a->title), 'регламент'))) {
            $p = $a->parent?->title ?? 'ROOT';
            $len = mb_strlen((string) ($a->markdown_content ?? ''));
            echo "  {$a->id}\t{$p}\t{$a->title}\t".($a->status?->value ?? '')."\tmd={$len}\n";
        }
    }
}

echo "\n=== roots ===\n";
foreach ($all->whereNull('parent_id') as $a) {
    echo "  {$a->id}\t{$a->title}\n";
}

echo "\n=== under Регламенты ===\n";
foreach ($all as $a) {
    $p = matchKey($a->parent?->title ?? '');
    if (str_contains($p, 'регламент') || matchKey($a->title) === 'регламенты работы') {
        echo "  {$a->id}\t".($a->parent?->title ?? 'ROOT')."\t{$a->title}\n";
    }
}

echo "\n=== day4/5/7/9 exact ===\n";
foreach ($all as $a) {
    $t = matchKey($a->title);
    if (preg_match('/день [4579]/u', $t)) {
        echo "  {$a->id}\t".($a->parent?->title ?? 'ROOT')."\t«{$a->title}»\n";
    }
}

echo "\n=== funnel children ===\n";
foreach ($all as $a) {
    if ($a->parent && str_contains(matchKey($a->parent->title), 'воронка')) {
        echo "  {$a->id}\t«{$a->title}»\n";
    }
}
