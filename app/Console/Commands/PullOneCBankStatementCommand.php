<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingOneCPullService;
use App\Services\OneC\OneCBridgeCheckService;
use App\Services\OneC\OneCPublicationCatalog;
use App\Support\SystemActorUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class PullOneCBankStatementCommand extends Command
{
    /**
     * Cron без --from: сколько дней назад смотреть (документы в 1С часто появляются с датой операции позже).
     */
    public const DEFAULT_LOOKBACK_DAYS = 7;

    protected $signature = 'management-accounting:pull-one-c-bank
        {--from= : Дата начала YYYY-MM-DD (включительно)}
        {--to= : Дата конца YYYY-MM-DD (включительно)}
        {--company= : Публикация (autalliance|gross|profsfera); пусто = все}
        {--allocate : Авторазнести по подсказкам матчинга}
        {--min-confidence=55 : Минимальная уверенность для авторазнесения}
        {--user= : ID пользователя-актора (иначе технический «Система»)}
        {--bridge-check : После pull проверить мост и эскалировать}';

    protected $description = 'Забрать банк из 1С OData в управленческий учёт CRM';

    public function handle(
        ManagementAccountingOneCPullService $pull,
        OneCPublicationCatalog $catalog,
        OneCBridgeCheckService $bridgeCheck,
    ): int {
        if (! (bool) config('one_c.enabled')) {
            $this->error('ONE_C_ENABLED=false — включите коннектор 1С.');

            return self::FAILURE;
        }

        $from = (string) ($this->option('from') ?: now()->subDays(self::DEFAULT_LOOKBACK_DAYS)->toDateString());
        $toInclusive = (string) ($this->option('to') ?: now()->toDateString());
        $toExclusive = date('Y-m-d', strtotime($toInclusive.' +1 day'));
        $allocate = (bool) $this->option('allocate');
        $minConfidence = (int) $this->option('min-confidence');

        $actor = $this->resolveActor();
        if ($actor === null) {
            $this->error('Не найден пользователь-актор.');

            return self::FAILURE;
        }

        $companyOpt = $this->option('company');
        $codes = is_string($companyOpt) && $companyOpt !== ''
            ? [$companyOpt]
            : array_map(static fn (array $p): string => $p['code'], $catalog->all());

        $exit = self::SUCCESS;
        foreach ($codes as $code) {
            $this->info("OData банк {$code} {$from}…{$toInclusive}, actor={$actor->id}, allocate=".($allocate ? 'yes' : 'no'));
            try {
                $result = $pull->pullAndImport(
                    $from,
                    $toExclusive,
                    $actor,
                    $allocate,
                    $minConfidence,
                    null,
                    $code,
                );
            } catch (InvalidArgumentException $e) {
                $message = "[{$code}] ".$e->getMessage();
                $this->warn($message);
                Log::warning('one_c.bank_pull_empty', [
                    'company' => $code,
                    'from' => $from,
                    'to' => $toInclusive,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            $import = $result['import'];
            $summary = sprintf(
                '[%s] Import #%d: fetched=%d created=%d skipped=%d allocated=%d pending=%d',
                $code,
                $import->id,
                $result['fetched'],
                $result['created'],
                $result['skipped'],
                $result['allocated'],
                $result['pending'],
            );
            $this->info($summary);
            Log::info('one_c.bank_pull', [
                'company' => $code,
                'from' => $from,
                'to' => $toInclusive,
                'import_id' => $import->id,
                'fetched' => $result['fetched'],
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'allocated' => $result['allocated'],
                'pending' => $result['pending'],
            ]);

            foreach ($result['allocation_errors'] as $error) {
                $this->warn('allocate: '.$error);
            }
        }

        if ($this->option('bridge-check')) {
            $check = $bridgeCheck->check(null);
            $this->info('Bridge: '.$check['summary_ru']);
            if ($check['status'] === 'error') {
                $exit = self::FAILURE;
            }
        }

        return $exit;
    }

    private function resolveActor(): ?User
    {
        $userId = $this->option('user');
        if ($userId !== null && $userId !== '') {
            return User::query()->find((int) $userId);
        }

        return SystemActorUser::resolve();
    }
}
