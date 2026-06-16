<?php

namespace App\Services;

use App\Models\BusinessProcess;
use App\Models\SalesScript;
use App\Support\BusinessProcessDefaultPlaybookLibrary;
use App\Support\BusinessProcessPlaybook;
use Illuminate\Support\Facades\Schema;

class BusinessProcessPlaybookSeederService
{
    /**
     * @return array{processes: int, stages: int, scripts_linked: int}
     */
    public function seed(bool $onlyEmpty = true): array
    {
        if (! Schema::hasTable('business_processes') || ! Schema::hasTable('business_process_stages')) {
            return ['processes' => 0, 'stages' => 0, 'scripts_linked' => 0];
        }

        $processesUpdated = 0;
        $stagesUpdated = 0;
        $scriptsLinked = 0;

        $processes = BusinessProcess::query()->with('stages')->get();

        foreach ($processes as $process) {
            $processDescription = BusinessProcessDefaultPlaybookLibrary::processDescriptions()[$process->slug] ?? null;

            if ($processDescription !== null && (! $onlyEmpty || blank($process->description))) {
                $process->description = BusinessProcessPlaybook::normalize($processDescription);
                $process->save();
                $processesUpdated++;
            }

            foreach ($process->stages as $stage) {
                $defaults = BusinessProcessDefaultPlaybookLibrary::forStage($process->slug, $stage->name);

                if ($defaults === null) {
                    continue;
                }

                $dirty = false;

                if (! $onlyEmpty || blank($stage->stage_goal)) {
                    if (filled($defaults['stage_goal'] ?? null)) {
                        $stage->stage_goal = $defaults['stage_goal'];
                        $dirty = true;
                    }
                }

                if (! $onlyEmpty || blank($stage->description)) {
                    if (filled($defaults['description'] ?? null)) {
                        $stage->description = BusinessProcessPlaybook::normalize($defaults['description']);
                        $dirty = true;
                    }
                }

                if (! $onlyEmpty || blank($stage->success_criteria)) {
                    if (filled($defaults['success_criteria'] ?? null)) {
                        $stage->success_criteria = BusinessProcessPlaybook::normalize($defaults['success_criteria']);
                        $dirty = true;
                    }
                }

                if (Schema::hasColumn('business_process_stages', 'auto_create_task')) {
                    if ((! $onlyEmpty || ! $stage->auto_create_task) && array_key_exists('auto_create_task', $defaults)) {
                        $stage->auto_create_task = (bool) $defaults['auto_create_task'];
                        $dirty = true;
                    }

                    if ((! $onlyEmpty || blank($stage->task_title_template)) && filled($defaults['task_title_template'] ?? null)) {
                        $stage->task_title_template = $defaults['task_title_template'];
                        $dirty = true;
                    }

                    if ((! $onlyEmpty || blank($stage->task_description_template)) && filled($defaults['task_description_template'] ?? null)) {
                        $stage->task_description_template = BusinessProcessPlaybook::normalize($defaults['task_description_template']);
                        $dirty = true;
                    }

                    if ((! $onlyEmpty || (int) ($stage->task_due_days_offset ?? 0) === 0) && array_key_exists('task_due_days_offset', $defaults)) {
                        $stage->task_due_days_offset = (int) $defaults['task_due_days_offset'];
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $stage->save();
                    $stagesUpdated++;
                }

                if (Schema::hasColumn('business_process_stages', 'sales_script_id') && $stage->sales_script_id === null) {
                    $scriptId = $this->suggestScriptIdForStage($process->slug, $stage->name);
                    if ($scriptId !== null) {
                        $stage->sales_script_id = $scriptId;
                        $stage->save();
                        $scriptsLinked++;
                    }
                }
            }
        }

        return [
            'processes' => $processesUpdated,
            'stages' => $stagesUpdated,
            'scripts_linked' => $scriptsLinked,
        ];
    }

    private function suggestScriptIdForStage(string $processSlug, string $stageName): ?int
    {
        if (! Schema::hasTable('sales_scripts')) {
            return null;
        }

        $keywords = match ($processSlug) {
            'transport-intake' => match ($stageName) {
                'Получение деталей по перевозке' => ['квалиф', 'qualif', 'детал'],
                'Согласование цены' => ['возраж', 'objection', 'соглас'],
                default => [],
            },
            default => [],
        };

        if ($keywords === []) {
            return null;
        }

        $scripts = SalesScript::query()->orderBy('title')->get(['id', 'title']);

        foreach ($keywords as $keyword) {
            $match = $scripts->first(function (SalesScript $script) use ($keyword): bool {
                return mb_stripos((string) $script->title, $keyword) !== false;
            });

            if ($match !== null) {
                return (int) $match->id;
            }
        }

        return $scripts->first()?->id;
    }
}
