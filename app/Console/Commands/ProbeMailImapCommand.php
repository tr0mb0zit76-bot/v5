<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Commercial\MailInboxSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class ProbeMailImapCommand extends Command
{
    protected $signature = 'mail:probe-imap
                            {--user= : ID пользователя (обязательно)}
                            {--days=7 : Глубина поиска SINCE в днях}';

    protected $description = 'Диагностика IMAP: список папок и число писем SINCE (без записи в CRM)';

    public function handle(MailInboxSyncService $syncService): int
    {
        if (! function_exists('imap_open')) {
            $this->error('PHP extension imap не установлена.');

            return self::FAILURE;
        }

        $userId = $this->option('user');

        if (! is_numeric($userId) || (int) $userId <= 0) {
            $this->error('Укажите --user=ID (например --user=31).');

            return self::FAILURE;
        }

        $eligibility = $syncService->syncEligibilityForUserId((int) $userId);

        if (! $eligibility['eligible']) {
            $this->error('Пользователь не готов: '.implode(' ', $eligibility['reasons']));

            return self::FAILURE;
        }

        $user = User::query()->findOrFail((int) $userId);
        $password = $user->mail_imap_secret;

        if (! is_string($password) || $password === '') {
            $this->error('Не удалось расшифровать mail_imap_secret (проверьте APP_KEY).');

            return self::FAILURE;
        }

        $days = max(1, min(365, (int) $this->option('days')));
        $since = CarbonImmutable::now()->subDays($days);
        $searchDate = $since->format('d-M-Y');
        $prefix = $this->mailboxPrefix();
        $username = (string) $user->email;

        $this->info("Ящик: {$username}");
        $this->line("IMAP: {$prefix} (SINCE \"{$searchDate}\")");
        $this->newLine();

        $inboxMailbox = $prefix.'INBOX';
        $connection = @imap_open($inboxMailbox, $username, $password, OP_READONLY, 1, [
            'DISABLE_AUTHENTICATOR' => 'GSSAPI',
        ]);

        if ($connection === false) {
            $this->error('Не удалось открыть INBOX: '.trim((string) imap_last_error()));

            return self::FAILURE;
        }

        $this->info('Папки на сервере:');

        /** @var list<string>|false $folders */
        $folders = imap_list($connection, $prefix, '*');

        if ($folders === false) {
            $this->warn('  imap_list не вернул список.');
        } else {
            foreach ($folders as $folder) {
                $short = str_starts_with($folder, $prefix)
                    ? substr($folder, strlen($prefix))
                    : $folder;
                $this->line('  - '.$short);
            }
        }

        $this->newLine();
        $this->info('Кандидаты из config/mail_sync.php:');

        $candidates = array_values(array_unique(array_filter([
            ...config('mail_sync.folders.inbound', ['INBOX']),
            ...config('mail_sync.folders.outbound', []),
        ])));

        $anySince = 0;

        foreach ($candidates as $folder) {
            $result = $this->probeFolder($prefix, $username, $password, (string) $folder, $searchDate);
            $anySince += $result['since_count'];

            if ($result['opened']) {
                $this->line(sprintf(
                    '  %-20s  всего: %d, SINCE %d дн.: %d, без Message-ID (оценка): %d',
                    $folder,
                    $result['total'],
                    $days,
                    $result['since_count'],
                    $result['without_message_id'],
                ));
            } else {
                $this->warn("  {$folder}: не открылась — {$result['error']}");
            }
        }

        imap_close($connection);

        $this->newLine();

        if ($anySince === 0) {
            $this->warn('На сервере IMAP за период нет писем (или неверные имена папок).');
            $this->line('Проверьте веб-почту reg.ru / Thunderbird тем же логином.');
            $this->line('Попробуйте: php artisan mail:sync --user='.$user->id.' --days=30');
            $this->line('Если в панели другой IMAP-хост — задайте MAIL_SYNC_IMAP_HOST в .env.');

            return self::FAILURE;
        }

        $this->info("На сервере найдено писем SINCE (сумма по папкам): {$anySince}.");
        $this->line('Если sync всё ещё 0 — обновите код (521af61+) и смотрите вывод mail:sync.');

        return self::SUCCESS;
    }

    private function mailboxPrefix(): string
    {
        return sprintf(
            '{%s:%d/imap/%s%s}',
            config('mail_sync.imap.host'),
            (int) config('mail_sync.imap.port', 993),
            config('mail_sync.imap.encryption', 'ssl'),
            config('mail_sync.imap.validate_cert', true) ? '' : '/novalidate-cert',
        );
    }

    /**
     * @return array{opened: bool, total: int, since_count: int, without_message_id: int, error: string|null}
     */
    private function probeFolder(
        string $prefix,
        string $username,
        string $password,
        string $folder,
        string $searchDate,
    ): array {
        $mailbox = $prefix.$folder;
        $connection = @imap_open($mailbox, $username, $password, OP_READONLY, 1, [
            'DISABLE_AUTHENTICATOR' => 'GSSAPI',
        ]);

        if ($connection === false) {
            return [
                'opened' => false,
                'total' => 0,
                'since_count' => 0,
                'without_message_id' => 0,
                'error' => trim((string) imap_last_error()) ?: 'ошибка imap_open',
            ];
        }

        $total = imap_num_msg($connection);
        /** @var list<int>|false $uids */
        $uids = imap_search($connection, 'SINCE "'.$searchDate.'"', SE_UID);
        $sinceCount = is_array($uids) ? count($uids) : 0;
        $withoutMessageId = 0;

        if (is_array($uids)) {
            foreach (array_slice($uids, 0, min(20, count($uids))) as $uid) {
                $header = imap_fetchheader($connection, (int) $uid, FT_UID);

                if ($header === false || ! preg_match('/^Message-ID:\s*.+/im', $header)) {
                    $withoutMessageId++;
                }
            }
        }

        imap_close($connection);

        return [
            'opened' => true,
            'total' => max(0, $total),
            'since_count' => $sinceCount,
            'without_message_id' => $withoutMessageId,
            'error' => null,
        ];
    }
}
