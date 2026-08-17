<?php

declare(strict_types=1);

/**
 * Sync «Лиды — инструкция для пользователя» from docs/lead-user-guide.md
 * Prod article id=19; locally falls back to title match.
 *
 * Usage: php scripts/sync-leads-sales-book-article.php [--dry-run]
 */
require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

use App\Models\SalesBookArticle;
use App\Models\User;
use App\Services\SalesBook\SalesBookBlockSnapshotService;
use App\Support\SalesBookContentNormalizer;
use Illuminate\Contracts\Console\Kernel;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$articleId = 19;
$sourcePath = __DIR__.'/../docs/lead-user-guide.md';

if (! is_readable($sourcePath)) {
    fwrite(STDERR, "Source not found: {$sourcePath}\n");
    exit(1);
}

$markdown = file_get_contents($sourcePath);
if ($markdown === false) {
    fwrite(STDERR, "Failed to read source.\n");
    exit(1);
}

$markdown = adaptForSalesBook($markdown);

/** @var SalesBookArticle|null $article */
$article = SalesBookArticle::query()->find($articleId)
    ?? SalesBookArticle::query()->where('title', 'Лиды — инструкция для пользователя')->first();
if ($article === null) {
    fwrite(STDERR, "Article {$articleId} (or title «Лиды — инструкция для пользователя») not found.\n");
    exit(1);
}

$normalizer = app(SalesBookContentNormalizer::class);
$blockSnapshot = app(SalesBookBlockSnapshotService::class);
$normalized = $normalizer->normalize($markdown);

if ($dryRun) {
    echo "DRY RUN — article {$article->id}: «{$article->title}»\n";
    echo 'Current length: '.strlen((string) $article->markdown_content)." bytes\n";
    echo 'New length: '.strlen($normalized)." bytes\n";
    echo substr($normalized, 0, 800)."\n...\n";
    exit(0);
}

$adminId = User::query()->orderBy('id')->value('id');

$article->update([
    'markdown_content' => $normalized,
    'blocks_snapshot' => $blockSnapshot->fromStoredMarkdown($normalized),
    'updated_by' => $adminId,
]);

echo "Updated Sales Book article {$article->id}: «{$article->title}» (".strlen($normalized)." bytes)\n";

function adaptForSalesBook(string $markdown): string
{
    $markdown = preg_replace(
        '/^# Лиды — инструкция для менеджера/mu',
        '# Лиды — инструкция для пользователя',
        $markdown,
        1,
    ) ?? $markdown;

    $markdown = str_replace(
        '**Полное описание механизма** (статусы, бизнес-процесс, напоминания, автоматика): [leads-mechanism.md](./leads-mechanism.md).',
        'Документ описывает, как вести входящую заявку в CRM: от создания лида до коммерческого предложения и конвертации в заказ. Грамотная работа с карточкой экономит время на следующем касании, КП и заказе и не даёт забыть перезвон. Подробный регламент (статусы, бизнес-процесс, nudges) — у администратора в git-документе `leads-mechanism.md` или по запросу в IT.',
        $markdown,
    );

    $markdown = str_replace(
        'См. также: [Мастер заказов](order-wizard-user-guide.md) — после конвертации лида.',
        'См. также в Книге продаж: **Мастер заказов — инструкция для пользователя** — что происходит после конвертации лида.',
        $markdown,
    );

    $markdown = preg_replace(
        '/\[разделы 4–11 в leads-mechanism\.md\]\(\.\/leads-mechanism\.md\)/',
        'полное описание в регламенте по лидам',
        $markdown,
    ) ?? $markdown;

    $markdown = preg_replace(
        '/\[напоминания\]\(\.\/leads-mechanism\.md#8-напоминания-nudges-и-задачи\)/',
        'раздел «Напоминания» в регламенте по лидам',
        $markdown,
    ) ?? $markdown;

    return $markdown;
}
