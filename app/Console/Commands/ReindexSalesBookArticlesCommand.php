<?php

namespace App\Console\Commands;

use App\Jobs\ReindexSalesBookArticlesJob;
use Illuminate\Console\Command;

class ReindexSalesBookArticlesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'sales-book:reindex {--sync : Выполнить синхронно без очереди}';

    /**
     * @var string
     */
    protected $description = 'Переиндексировать порядок статей книги продаж (sort_order по родителям)';

    public function handle(): int
    {
        if ($this->option('sync')) {
            ReindexSalesBookArticlesJob::dispatchSync();
            $this->info('Книга продаж переиндексирована синхронно.');

            return self::SUCCESS;
        }

        ReindexSalesBookArticlesJob::dispatch();
        $this->info('Задача переиндексации книги продаж поставлена в очередь.');

        return self::SUCCESS;
    }
}
