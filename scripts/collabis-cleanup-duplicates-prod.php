<?php

declare(strict_types=1);

/**
 * Fix Collabis import duplicates for «Ускоряемся в продажах» / «Профи» on prod.
 *
 * - Restore original hubs (id 33, 38) under ✈️Экспресс-введении
 * - Delete empty draft hub duplicates (169, 186)
 * - Delete root orphan duplicate subtrees (118+children, 122+children)
 *
 * Usage: php scripts/collabis-cleanup-duplicates-prod.php [--dry-run]
 */
$dryRun = in_array('--dry-run', $argv, true);

$root = getenv('CRM_ROOT') ?: dirname(__DIR__);
if (! is_dir($root.'/vendor')) {
    fwrite(STDERR, "CRM root not found: {$root}\n");
    exit(1);
}

require $root.'/vendor/autoload.php';
$app = require_once $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SalesBookArticle;
use App\Services\SalesBookArticleTreeService;

const EXPRESS_MATCH = 'экспресс-введении';

/** @var list<int> */
const RESTORE_TO_EXPRESS_IDS = [33, 38];

/** @var list<int> */
const DELETE_LEAF_IDS = [169, 186];

/** @var list<int> */
const DELETE_SUBTREE_ROOT_IDS = [118, 122];

function deleteSubtree(int $articleId, bool $dryRun): int
{
    $children = SalesBookArticle::query()
        ->where('parent_id', $articleId)
        ->orderByDesc('id')
        ->pluck('id')
        ->all();

    $deleted = 0;
    foreach ($children as $childId) {
        $deleted += deleteSubtree((int) $childId, $dryRun);
    }

    $article = SalesBookArticle::query()->find($articleId);
    if ($article === null) {
        return $deleted;
    }

    echo 'DELETE: '.$article->id.' «'.$article->title.'»'.PHP_EOL;
    if (! $dryRun) {
        $article->delete();
    }

    return $deleted + 1;
}

$tree = app(SalesBookArticleTreeService::class);

$express = SalesBookArticle::query()
    ->whereNull('parent_id')
    ->get()
    ->first(fn (SalesBookArticle $article): bool => str_contains(mb_strtolower($article->title, 'UTF-8'), EXPRESS_MATCH));

if ($express === null) {
    fwrite(STDERR, "Express root not found.\n");
    exit(1);
}

echo 'Express hub: '.$express->id.' «'.$express->title.'»'.PHP_EOL;

foreach (RESTORE_TO_EXPRESS_IDS as $articleId) {
    $article = SalesBookArticle::query()->find($articleId);
    if ($article === null) {
        fwrite(STDERR, "Missing article id={$articleId}\n");
        continue;
    }

    if ((int) $article->parent_id === (int) $express->id) {
        echo "SKIP already under express: {$article->title}\n";
        continue;
    }

    $targetIndex = (int) SalesBookArticle::query()->where('parent_id', $express->id)->count();
    echo "RESTORE to express: {$article->id} «{$article->title}» (from parent_id={$article->parent_id})\n";

    if (! $dryRun) {
        $tree->moveArticle($article, $express->id, $targetIndex);
    }
}

$deleted = 0;
foreach (DELETE_LEAF_IDS as $articleId) {
    $article = SalesBookArticle::query()->find($articleId);
    if ($article === null) {
        echo "SKIP missing leaf id={$articleId}\n";
        continue;
    }

    echo 'DELETE leaf: '.$article->id.' «'.$article->title.'»'.PHP_EOL;
    if (! $dryRun) {
        $article->delete();
    }
    $deleted++;
}

foreach (DELETE_SUBTREE_ROOT_IDS as $rootId) {
    $deleted += deleteSubtree($rootId, $dryRun);
}

echo 'Done. deleted='.$deleted.' dry_run='.($dryRun ? 'yes' : 'no').PHP_EOL;
