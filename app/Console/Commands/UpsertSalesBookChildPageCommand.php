<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Mcp\SalesBookMcpService;
use Illuminate\Console\Command;
use RuntimeException;

class UpsertSalesBookChildPageCommand extends Command
{
    protected $signature = 'sales-book:upsert-child-page {--parent=} {--title=} {--file=} {--user=} {--ensure-parent}';

    protected $description = 'Создать или обновить дочернюю страницу Книги продаж из Markdown-файла';

    public function handle(SalesBookMcpService $salesBook): int
    {
        $parentTitle = trim((string) $this->option('parent'));
        $childTitle = trim((string) $this->option('title'));
        $file = trim((string) $this->option('file'));

        if ($parentTitle === '' || $childTitle === '' || $file === '') {
            $this->error('Укажите --parent, --title и --file.');

            return self::FAILURE;
        }

        $absolutePath = base_path($file);
        if (! is_file($absolutePath)) {
            $this->error("Файл не найден: {$absolutePath}");

            return self::FAILURE;
        }

        $user = $this->resolveUser();
        if ($user === null) {
            $this->error('Не найден пользователь для записи (укажите --user=email активного админа).');

            return self::FAILURE;
        }

        try {
            $result = $salesBook->upsertChildPage(
                $user,
                $parentTitle,
                $childTitle,
                (string) file_get_contents($absolutePath),
                null,
                [],
                (bool) $this->option('ensure-parent'),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s страница «%s» под «%s» (id=%d).',
            $result['action'] === 'created' ? 'Создана' : 'Обновлена',
            $result['title'],
            $result['parent_title'],
            $result['article_id'],
        ));
        $this->line($result['book_url']);

        return self::SUCCESS;
    }

    private function resolveUser(): ?User
    {
        $email = trim((string) $this->option('user'));
        if ($email !== '') {
            return User::query()->where('email', $email)->where('is_active', true)->first();
        }

        return User::query()
            ->where('is_active', true)
            ->whereHas('role', fn ($query) => $query->where('name', 'admin'))
            ->orderBy('id')
            ->first()
            ?? User::query()->where('is_active', true)->orderBy('id')->first();
    }
}
