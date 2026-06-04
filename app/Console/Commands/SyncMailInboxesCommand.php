<?php

namespace App\Console\Commands;

use App\Services\Commercial\MailInboxSyncService;
use Illuminate\Console\Command;

class SyncMailInboxesCommand extends Command
{
    protected $signature = 'mail:sync
                            {--user= : ID пользователя (один ящик)}
                            {--days= : Глубина синхронизации в днях}
                            {--verbose : Подробный лог по папкам IMAP}';

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

        if ($parsedUserId !== null && $parsedUserId > 0) {
            $eligibility = $syncService->syncEligibilityForUserId($parsedUserId);

            if (! $eligibility['eligible']) {
                $this->warn('Пользователь не готов к sync: '.implode(' ', $eligibility['reasons']));
                $this->line('IMAP: '.config('mail_sync.imap.host').':'.config('mail_sync.imap.port').' (логин = users.email)');

                return self::FAILURE;
            }
        }

        $result = $syncService->syncAllMailboxes($parsedUserId, $parsedDays, (bool) $this->option('verbose'));

        $processed = $result['users_processed'] ?? 0;
        $this->info("Импортировано: {$result['imported']}, пропущено (дубли): {$result['skipped']}, ящиков обработано: {$processed}.");

        if ($processed === 0 && ($parsedUserId === null || $parsedUserId <= 0)) {
            $this->warn('Нет активных пользователей с паролем почты (mail_imap_secret).');
        }

        foreach ($result['errors'] as $error) {
            if (str_starts_with($error, '[debug]')) {
                $this->line($error);
            } else {
                $this->warn($error);
            }
        }

        if ($result['errors'] !== [] && $result['imported'] === 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
