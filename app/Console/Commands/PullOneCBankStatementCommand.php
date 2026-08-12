<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ManagementAccounting\ManagementAccountingOneCPullService;
use App\Services\OneC\OneCBridgeCheckService;
use App\Services\OneC\OneCPublicationCatalog;
use Illuminate\Console\Command;
use InvalidArgumentException;

class PullOneCBankStatementCommand extends Command
{
    protected $signature = 'management-accounting:pull-one-c-bank
        {--from= : Дата начала YYYY-MM-DD (включительно)}
        {--to= : Дата конца YYYY-MM-DD (включительно)}
        {--company= : Публикация (autalliance|gross|profsfera); пусто = все}
        {--allocate : Авторазнести по подсказкам матчинга}
        {--min-confidence=55 : Минимальная уверенность для авторазнесения}
        {--user= : ID пользователя-актора (иначе management accounting / admin)}
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

        $from = (string) ($this->option('from') ?: now()->subDay()->toDateString());
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
                $this->warn("[{$code}] ".$e->getMessage());

                continue;
            }

            $import = $result['import'];
            $this->info(sprintf(
                '[%s] Import #%d: fetched=%d created=%d skipped=%d allocated=%d pending=%d',
                $code,
                $import->id,
                $result['fetched'],
                $result['created'],
                $result['skipped'],
                $result['allocated'],
                $result['pending'],
            ));

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

        return User::query()->where('can_management_accounting', true)->orderBy('id')->first()
            ?? User::query()
                ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
                ->orderBy('id')
                ->first()
            ?? User::query()->orderBy('id')->first();
    }
}
