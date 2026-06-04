<?php

namespace App\Console\Commands;

use App\Services\Commercial\MailInboxSyncService;
use Illuminate\Console\Command;

class SyncMailInboxesCommand extends Command
{
    protected $signature = 'mail:sync
                            {--user= : ID пользователя (один ящик)}
                            {--days= : Глубина синхронизации в днях}';

    protected $description = 'Синхронизирует переписку из IMAP (reg.ru) в CRM для активных пользователей с паролем почты';

    public function handle(MailInboxSyncService $syncService): int
    {
        if (! $syncService->tablesReady()) {
            $this->error('Таблицы почты не готовы. Выполните php artisan migrate.');

            return self::FAILURE;
        }

        $userId = $this->option('user');
        $days = $this->option('days');

        $parsedUserId = is_numeric($userId) ? (int) $userId : null;
        $parsedDays = is_numeric($days) ? (int) $days : null;

        $result = $syncService->syncAllMailboxes($parsedUserId, $parsedDays);

        $this->info("Импортировано: {$result['imported']}, пропущено (дубли): {$result['skipped']}.");

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        if ($result['errors'] !== [] && $result['imported'] === 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
