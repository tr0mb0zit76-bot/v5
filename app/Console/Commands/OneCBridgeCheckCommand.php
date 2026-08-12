<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OneC\OneCBridgeCheckService;
use App\Services\OneC\OneCBridgeHealthService;
use Illuminate\Console\Command;

class OneCBridgeCheckCommand extends Command
{
    protected $signature = 'one-c:bridge-check
        {--company= : Код публикации (autalliance|gross|profsfera)}
        {--user= : ID инициатора (иначе system)}
        {--no-escalate : Только вердикт без задач}';

    protected $description = 'Проверить мост CRM ↔ 1С и при проблемах создать задачу ответственному';

    public function handle(OneCBridgeCheckService $check): int
    {
        if (! (bool) config('one_c.enabled')) {
            $this->error('ONE_C_ENABLED=false');

            return self::FAILURE;
        }

        $company = $this->option('company');
        $company = is_string($company) && $company !== '' ? $company : null;

        $initiator = null;
        $userId = $this->option('user');
        if (is_string($userId) && $userId !== '') {
            $initiator = User::query()->find((int) $userId);
        }

        if ($this->option('no-escalate')) {
            $verdict = app(OneCBridgeHealthService::class)->evaluate($company);
            $this->line(json_encode($verdict, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $result = $check->check($initiator, $company);
        $this->info($result['summary_ru']);
        foreach ($result['companies'] as $companyRow) {
            $this->line(sprintf(
                '  [%s] %s odata=%s pending=%d issues=%d',
                $companyRow['code'],
                $companyRow['label'],
                $companyRow['odata_ok'] ? 'ok' : 'fail',
                $companyRow['pending_count'],
                count($companyRow['issues']),
            ));
        }
        if ($result['task_created'] !== null) {
            $this->warn('Задача #'.$result['task_created']['id'].': '.$result['task_created']['title']);
        }

        return $result['status'] === 'error' ? self::FAILURE : self::SUCCESS;
    }
}
