<?php

declare(strict_types=1);

/**
 * Place Collabis library sections under 👨‍🎓Глоссарий and remove duplicate hubs.
 *
 * Usage: CRM_ROOT=/path php scripts/collabis-fix-glossary-sections-prod.php [--dry-run]
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

const GLOSSARY_KEY = 'глоссарий';

/** Empty draft hub duplicates created by import (parent = glossary). */
const DELETE_EMPTY_DRAFT_IDS = [201, 205, 164, 200, 187, 228];

/** Draft duplicate subtrees at ROOT when canonical copy exists elsewhere. */
const DELETE_SUBTREE_ROOT_IDS = [207];

/** Duplicate empty hub under Терминология (Incoterms content lives under id 125). */
const DELETE_LEAF_IDS = [202];

/**
 * Canonical section hubs to move under glossary.
 *
 * @var array<int, string>
 */
const MOVE_TO_GLOSSARY_IDS = [
    114 => 'транспортные документы',
    116 => 'терминология',
    143 => 'финансовые документы',
    148 => 'сертификаты и декларации',
    173 => 'типы перевозок',
    74 => 'виды транспорта',
];

/** Incoterms hub → child of Терминология after move. */
const INCOTERMS_ID = 125;
const TERMINOLOGY_ID = 116;

function normalizeMatchKey(string $title): string
{
    $title = preg_replace('/[\x{E000}-\x{F8FF}\x{FE0F}\x{200D}]/u', '', $title) ?? $title;
    $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title) ?? $title;
    $title = preg_replace('/\s+/u', ' ', trim($title)) ?? trim($title);

    return mb_strtolower($title, 'UTF-8');
}

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

$glossary = SalesBookArticle::query()
    ->whereNull('parent_id')
    ->get()
    ->first(fn (SalesBookArticle $article): bool => normalizeMatchKey($article->title) === GLOSSARY_KEY);

if ($glossary === null) {
    fwrite(STDERR, "Glossary root not found.\n");
    exit(1);
}

echo 'Glossary: '.$glossary->id.' «'.$glossary->title.'»'.PHP_EOL;

$deleted = 0;

foreach (DELETE_EMPTY_DRAFT_IDS as $articleId) {
    $article = SalesBookArticle::query()->find($articleId);
    if ($article === null) {
        echo "SKIP missing empty draft id={$articleId}\n";
        continue;
    }

    $childCount = SalesBookArticle::query()->where('parent_id', $articleId)->count();
    if ($childCount > 0) {
        fwrite(STDERR, "Refusing to delete non-empty draft id={$articleId} (children={$childCount})\n");
        continue;
    }

    echo 'DELETE empty draft: '.$article->id.' «'.$article->title.'»'.PHP_EOL;
    if (! $dryRun) {
        $article->delete();
    }
    $deleted++;
}

foreach (DELETE_LEAF_IDS as $articleId) {
    $article = SalesBookArticle::query()->find($articleId);
    if ($article === null) {
        continue;
    }

    $childCount = SalesBookArticle::query()->where('parent_id', $articleId)->count();
    if ($childCount > 0) {
        $deleted += deleteSubtree($articleId, $dryRun);
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

$moved = 0;
foreach (MOVE_TO_GLOSSARY_IDS as $articleId => $label) {
    $article = SalesBookArticle::query()->find($articleId);
    if ($article === null) {
        fwrite(STDERR, "Missing section id={$articleId} ({$label})\n");
        continue;
    }

    if ((int) $article->parent_id === (int) $glossary->id) {
        echo "SKIP already under glossary: {$article->title}\n";
        continue;
    }

    $targetIndex = (int) SalesBookArticle::query()->where('parent_id', $glossary->id)->count();
    echo "MOVE to glossary: {$article->id} «{$article->title}» (from parent_id={$article->parent_id})\n";

    if (! $dryRun) {
        $tree->moveArticle($article, $glossary->id, $targetIndex);
    }

    $moved++;
}

$incoterms = SalesBookArticle::query()->find(INCOTERMS_ID);
$terminology = SalesBookArticle::query()->find(TERMINOLOGY_ID);

if ($incoterms !== null && $terminology !== null && (int) $incoterms->parent_id !== (int) $terminology->id) {
    $targetIndex = (int) SalesBookArticle::query()->where('parent_id', $terminology->id)->count();
    echo "MOVE incoterms: {$incoterms->id} «{$incoterms->title}» under «{$terminology->title}»\n";

    if (! $dryRun) {
        $tree->moveArticle($incoterms, $terminology->id, $targetIndex);
    }

    $moved++;
}

echo 'Done. moved='.$moved.' deleted='.$deleted.' dry_run='.($dryRun ? 'yes' : 'no').PHP_EOL;
