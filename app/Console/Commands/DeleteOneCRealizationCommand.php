<?php

namespace App\Console\Commands;

use App\Services\OneC\OneCRealizationSyncService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeleteOneCRealizationCommand extends Command
{
    protected $signature = 'one-c:delete-realization
        {ref : Ref_Key реализации в 1С (GUID)}';

    protected $description = 'Удалить непроведённую реализацию в 1С (аварийная очистка / смоук)';

    public function handle(OneCRealizationSyncService $sync): int
    {
        $ref = (string) $this->argument('ref');

        try {
            $result = $sync->deleteRealizationByExternalRef($ref);
        } catch (ValidationException $e) {
            $this->error(collect($e->errors())->flatten()->implode('; '));

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $result['deleted']) {
            $this->warn('Документ в 1С не найден (уже удалён). Связи CRM при необходимости помечены.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Помечена на удаление непроведённая реализация %s (ref=%s)',
            $result['number'] ?? '—',
            $ref,
        ));

        return self::SUCCESS;
    }
}
