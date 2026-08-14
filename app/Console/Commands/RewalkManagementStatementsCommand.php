<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingStatementRewalkService;
use App\Support\SystemActorUser;
use Illuminate\Console\Command;

class RewalkManagementStatementsCommand extends Command
{
    protected $signature = 'management-accounting:rewalk-statements
        {--min-confidence=55 : Минимальная уверенность для авторазнесения}
        {--user= : ID актора (иначе технический «Система»)}';

    protected $description = 'Пройти выписки УУ: погасить дубли, снять ошибочный разнос на статью, перематчить pending';

    public function handle(ManagementAccountingStatementRewalkService $rewalk): int
    {
        $userId = $this->option('user');
        $actor = is_numeric($userId)
            ? User::query()->find((int) $userId)
            : SystemActorUser::resolve();

        if ($actor === null) {
            $this->error('Не найден пользователь-актор.');

            return self::FAILURE;
        }

        $minConfidence = max(1, (int) $this->option('min-confidence'));
        $stats = $rewalk->rewalk($actor, $minConfidence);
        $this->line(json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
