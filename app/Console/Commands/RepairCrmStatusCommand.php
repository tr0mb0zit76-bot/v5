<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\Task;
use App\Support\LeadStatus;
use App\Support\TaskStatus;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

#[Signature('crm:repair-status
    {entity : Entity type: lead or task}
    {id : Entity id}
    {status : Target status}
    {--reason= : Required audit reason}
    {--user-id= : Optional user id for audit fields}
    {--dry-run : Show the planned change without writing it}')]
#[Description('Manually repair a CRM lead or task status with an audit trail')]
class RepairCrmStatusCommand extends Command
{
    public function handle(): int
    {
        $entity = strtolower((string) $this->argument('entity'));
        $id = (int) $this->argument('id');
        $status = (string) $this->argument('status');
        $reason = trim((string) $this->option('reason'));
        $userId = $this->option('user-id') !== null ? (int) $this->option('user-id') : null;
        $dryRun = (bool) $this->option('dry-run');

        if (! in_array($entity, ['lead', 'task'], true)) {
            $this->error('Entity must be `lead` or `task`.');

            return self::FAILURE;
        }

        if ($reason === '') {
            $this->error('Pass --reason="..." so the manual repair is auditable.');

            return self::FAILURE;
        }

        return $entity === 'lead'
            ? $this->repairLead($id, $status, $reason, $userId, $dryRun)
            : $this->repairTask($id, $status, $reason, $userId, $dryRun);
    }

    private function repairLead(int $id, string $status, string $reason, ?int $userId, bool $dryRun): int
    {
        if (! in_array($status, LeadStatus::values(), true)) {
            $this->error('Invalid lead status. Allowed: '.implode(', ', LeadStatus::values()));

            return self::FAILURE;
        }

        $lead = Lead::query()->find($id);
        if ($lead === null) {
            $this->error("Lead #{$id} was not found.");

            return self::FAILURE;
        }

        $from = (string) $lead->status;
        $this->line("Lead #{$lead->id}: {$from} -> {$status}");
        $this->line("Reason: {$reason}");

        if ($dryRun) {
            $this->info('Dry run: no changes were written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($lead, $status, $reason, $userId, $from): void {
            $payload = ['status' => $status];
            if (Schema::hasColumn('leads', 'updated_by')) {
                $payload['updated_by'] = $userId;
            }

            $lead->forceFill($payload)->save();

            if (Schema::hasTable('lead_activities')) {
                $lead->activities()->create([
                    'type' => 'status_change',
                    'subject' => 'Статус лида исправлен вручную',
                    'content' => sprintf(
                        'Статус исправлен вручную: «%s» → «%s». Причина: %s',
                        LeadStatus::label($from),
                        LeadStatus::label($status),
                        $reason
                    ),
                    'created_by' => $userId,
                ]);
            }
        });

        $this->info('Lead status repaired.');

        return self::SUCCESS;
    }

    private function repairTask(int $id, string $status, string $reason, ?int $userId, bool $dryRun): int
    {
        if (! in_array($status, TaskStatus::values(), true)) {
            $this->error('Invalid task status. Allowed: '.implode(', ', TaskStatus::values()));

            return self::FAILURE;
        }

        $task = Task::query()->find($id);
        if ($task === null) {
            $this->error("Task #{$id} was not found.");

            return self::FAILURE;
        }

        $from = (string) $task->status;
        $this->line("Task #{$task->id}: {$from} -> {$status}");
        $this->line("Reason: {$reason}");

        if ($dryRun) {
            $this->info('Dry run: no changes were written.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($task, $status, $reason, $userId, $from): void {
            $task->forceFill([
                'status' => $status,
                'completed_at' => $status === 'done' ? now() : null,
            ])->save();

            if (Schema::hasTable('task_events')) {
                $task->events()->create([
                    'user_id' => $userId,
                    'type' => 'status_repaired',
                    'title' => 'Статус задачи исправлен вручную',
                    'description' => sprintf(
                        'Статус исправлен вручную: «%s» → «%s». Причина: %s',
                        TaskStatus::label($from),
                        TaskStatus::label($status),
                        $reason
                    ),
                    'meta' => [
                        'from' => $from,
                        'to' => $status,
                        'reason' => $reason,
                    ],
                ]);
            }
        });

        $this->info('Task status repaired.');

        return self::SUCCESS;
    }
}
