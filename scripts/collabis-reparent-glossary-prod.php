<?php

declare(strict_types=1);

/**
 * Reparent Collabis library sections on prod so they sit under 👨‍🎓Глоссарий.
 *
 * Usage (on server):
 *   php scripts/collabis-reparent-glossary-prod.php [--dry-run]
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

const GLOSSARY_TITLE = '👨‍🎓Глоссарий';
const TEMPLATES_TITLE = '✒️Шаблоны документов';

/** @var list<string> */
const MOVE_TO_GLOSSARY_KEYS = [
    'инкотермс',
];

/** @var list<string> */
const MOVE_TO_TEMPLATES_KEYS = [
    'b/l',
    'invoice',
    'packing',
    'договор-заявка',
    'тн',
    'шаблон коммерческого предложения',
    'cmr',
    'чек-лист сбора данных по клиенту',
    'шаблон сбора данных по перевозке',
];

function normalizeMatchKey(string $title): string
{
    $title = preg_replace('/[\x{E000}-\x{F8FF}\x{FE0F}\x{200D}]/u', '', $title) ?? $title;
    $title = preg_replace('/[^\p{L}\p{N}\s]/u', '', $title) ?? $title;
    $title = preg_replace('/\s+/u', ' ', trim($title)) ?? trim($title);

    return mb_strtolower($title, 'UTF-8');
}

function findArticleByMatchKey(string $matchKey): ?SalesBookArticle
{
    static $byKey = null;

    if ($byKey === null) {
        $byKey = [];
        foreach (SalesBookArticle::query()->get(['id', 'title', 'parent_id', 'sort_order']) as $article) {
            $key = normalizeMatchKey($article->title);
            if (! array_key_exists($key, $byKey)) {
                $byKey[$key] = $article;
            }
        }
    }

    return $byKey[$matchKey] ?? null;
}

function findRootArticle(string $title): ?SalesBookArticle
{
    foreach (SalesBookArticle::query()->whereNull('parent_id')->get() as $article) {
        if (normalizeMatchKey($article->title) === normalizeMatchKey($title)) {
            return $article;
        }
    }

    return findArticleByMatchKey(normalizeMatchKey($title));
}

$tree = app(SalesBookArticleTreeService::class);
$glossary = findRootArticle(GLOSSARY_TITLE);

if ($glossary === null) {
    fwrite(STDERR, "Glossary root not found.\n");
    exit(1);
}

$moved = 0;
$skipped = 0;

foreach (MOVE_TO_GLOSSARY_KEYS as $matchKey) {
    $article = findArticleByMatchKey($matchKey);
    if ($article === null) {
        fwrite(STDERR, "NOT FOUND: {$matchKey}\n");
        continue;
    }

    if ((int) $article->parent_id === (int) $glossary->id) {
        echo "SKIP already under glossary: {$article->title}\n";
        $skipped++;

        continue;
    }

    $targetIndex = (int) SalesBookArticle::query()->where('parent_id', $glossary->id)->count();
    echo "MOVE to glossary: {$article->title} (from parent_id={$article->parent_id})\n";

    if (! $dryRun) {
        $tree->moveArticle($article, $glossary->id, $targetIndex);
    }

    $moved++;
}

$templates = findArticleByMatchKey(normalizeMatchKey(TEMPLATES_TITLE));

if ($templates === null) {
    fwrite(STDERR, "Templates hub not found — skipping template reparent.\n");
} else {
    foreach (MOVE_TO_TEMPLATES_KEYS as $matchKey) {
        $article = findArticleByMatchKey($matchKey);
        if ($article === null) {
            fwrite(STDERR, "NOT FOUND template: {$matchKey}\n");
            continue;
        }

        if ((int) $article->parent_id === (int) $templates->id) {
            echo "SKIP already under templates: {$article->title}\n";
            $skipped++;

            continue;
        }

        $targetIndex = (int) SalesBookArticle::query()->where('parent_id', $templates->id)->count();
        echo "MOVE to templates: {$article->title} (from parent_id={$article->parent_id})\n";

        if (! $dryRun) {
            $tree->moveArticle($article, $templates->id, $targetIndex);
        }

        $moved++;
    }
}

echo "Done. moved={$moved} skipped={$skipped} dry_run=".($dryRun ? 'yes' : 'no')."\n";
