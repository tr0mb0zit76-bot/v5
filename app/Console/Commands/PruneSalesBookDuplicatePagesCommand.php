<?php

namespace App\Console\Commands;

use App\Models\SalesBookArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class PruneSalesBookDuplicatePagesCommand extends Command
{
    protected $signature = 'sales-book:prune-duplicate-pages
                            {--title= : Точное название страницы}
                            {--parent-id= : ID родителя (пусто = корень)}
                            {--keep= : ID страницы, которую оставить}
                            {--dry-run : Только показать, без удаления}';

    protected $description = 'Удалить дубликаты страниц Книги продаж с одинаковым названием и родителем';

    public function handle(): int
    {
        $title = trim((string) $this->option('title'));

        if ($title === '') {
            $this->error('Укажите --title.');

            return self::FAILURE;
        }

        $parentId = $this->option('parent-id');
        $parentId = $parentId === null || $parentId === '' ? null : (int) $parentId;
        $keepId = $this->option('keep') !== null && $this->option('keep') !== ''
            ? (int) $this->option('keep')
            : null;
        $dryRun = (bool) $this->option('dry-run');

        $query = SalesBookArticle::query()->where('title', $title);

        if ($parentId === null) {
            $query->whereNull('parent_id');
        } else {
            $query->where('parent_id', $parentId);
        }

        /** @var Collection<int, SalesBookArticle> $articles */
        $articles = $query->orderBy('id')->get();

        if ($articles->count() <= 1) {
            $this->info('Дубликатов не найдено.');

            return self::SUCCESS;
        }

        $keep = $keepId !== null
            ? $articles->firstWhere('id', $keepId)
            : $this->resolveKeepArticle($articles);

        if ($keep === null) {
            $this->error('Не удалось определить страницу для сохранения. Укажите --keep=ID.');

            return self::FAILURE;
        }

        $deleted = 0;

        foreach ($articles as $article) {
            if ($article->id === $keep->id) {
                $this->line("  keep id={$article->id} «{$article->title}»");

                continue;
            }

            if ($article->children()->exists()) {
                $this->warn("  skip id={$article->id}: есть дочерние страницы");

                continue;
            }

            if (Schema::hasTable('sales_book_quiz_attempts')
                && $article->quizAttempts()->exists()) {
                $this->warn("  skip id={$article->id}: есть попытки тестов");

                continue;
            }

            $this->info("  delete id={$article->id} «{$article->title}»");

            if (! $dryRun) {
                $article->delete();
            }

            $deleted++;
        }

        $this->newLine();
        $this->info($dryRun
            ? "Dry-run: будет удалено страниц — {$deleted}."
            : "Удалено страниц — {$deleted}.");

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, SalesBookArticle>  $articles
     */
    private function resolveKeepArticle($articles): ?SalesBookArticle
    {
        return $articles
            ->sort(function (SalesBookArticle $a, SalesBookArticle $b): int {
                $childDiff = $b->children()->count() <=> $a->children()->count();

                if ($childDiff !== 0) {
                    return $childDiff;
                }

                return $a->id <=> $b->id;
            })
            ->first();
    }
}
