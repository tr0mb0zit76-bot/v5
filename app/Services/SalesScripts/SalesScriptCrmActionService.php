<?php

namespace App\Services\SalesScripts;

use App\Enums\SalesPlaySessionOutcome;
use App\Models\Lead;
use App\Models\Order;
use App\Models\SalesScriptPlaySession;
use App\Models\Task;
use App\Support\TaskNumberGenerator;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SalesScriptCrmActionService
{
    public function __construct(
        private readonly TaskNumberGenerator $taskNumbers,
    ) {}

    public function syncAfterCompletion(SalesScriptPlaySession $session): void
    {
        DB::transaction(function () use ($session): void {
            $lockedSession = SalesScriptPlaySession::query()
                ->whereKey($session->id)
                ->lockForUpdate()
                ->first();

            if ($lockedSession === null || ! $lockedSession->isComplete() || $lockedSession->crm_synced_at !== null) {
                return;
            }

            $this->syncLockedSession($lockedSession);
        });
    }

    private function syncLockedSession(SalesScriptPlaySession $session): void
    {
        $session->loadMissing([
            'fieldValues.captureField',
            'primaryReactionClass',
            'version.script',
            'order.lead',
        ]);

        $lead = $this->resolveLead($session);
        $fieldValues = $this->fieldValuesByCode($session);
        $nextStepAt = $this->parseNextStepDate($fieldValues['next_step_date'] ?? null);
        $summary = $this->summary($session, $fieldValues);

        if ($lead instanceof Lead && Schema::hasTable('lead_activities')) {
            $lead->activities()->create([
                'type' => 'note',
                'subject' => $session->is_trainer ? 'Итог тренажёра' : 'Итог прохождения скрипта',
                'content' => $summary,
                'next_action_at' => $nextStepAt,
                'created_by' => $session->user_id,
            ]);

            if ($nextStepAt instanceof CarbonInterface) {
                $lead->forceFill([
                    'next_contact_at' => $nextStepAt,
                    'updated_by' => $session->user_id,
                ])->save();
            }
        }

        if (! $nextStepAt instanceof CarbonInterface || ! Schema::hasTable('tasks')) {
            $session->forceFill(['crm_synced_at' => now()])->save();

            return;
        }

        Task::query()->create([
            'number' => $this->taskNumbers->next(),
            'title' => $this->taskTitle($session),
            'description' => $summary,
            'status' => 'new',
            'priority' => $this->taskPriority($session->outcome),
            'due_at' => $nextStepAt,
            'responsible_id' => $session->user_id,
            'created_by' => $session->user_id,
            'lead_id' => $lead?->id,
            'order_id' => $session->order_id,
            'contractor_id' => $session->contractor_id,
            'meta' => [
                'source' => 'sales_script_play_session',
                'sales_script_play_session_id' => $session->id,
                'sales_script_version_id' => $session->sales_script_version_id,
                'outcome' => $session->outcome?->value,
                'is_trainer' => (bool) $session->is_trainer,
            ],
        ]);

        $session->forceFill(['crm_synced_at' => now()])->save();
    }

    /**
     * @return array<string, string>
     */
    private function fieldValuesByCode(SalesScriptPlaySession $session): array
    {
        $values = [];

        foreach ($session->fieldValues as $fieldValue) {
            $code = $fieldValue->captureField?->code;
            if (! is_string($code) || $code === '') {
                continue;
            }

            $value = trim((string) $fieldValue->value);
            if ($value !== '') {
                $values[$code] = $value;
            }
        }

        return $values;
    }

    private function resolveLead(SalesScriptPlaySession $session): ?Lead
    {
        if ($session->lead_id !== null && Schema::hasTable('leads')) {
            return Lead::query()->find($session->lead_id);
        }

        if ($session->order_id !== null && Schema::hasTable('orders') && Schema::hasColumn('orders', 'lead_id')) {
            $order = $session->order;
            if (! $order instanceof Order) {
                $order = Order::query()->with('lead')->find($session->order_id);
            }

            if ($order?->lead instanceof Lead) {
                return $order->lead;
            }
        }

        return null;
    }

    private function parseNextStepDate(?string $value): ?CarbonInterface
    {
        $trimmed = trim((string) $value);
        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, string>  $fieldValues
     */
    private function summary(SalesScriptPlaySession $session, array $fieldValues): string
    {
        $lines = [
            ($session->is_trainer ? 'Тренажёр' : 'Скрипт').': '.($session->version?->script?->title ?? '—'),
            'Исход: '.$this->outcomeLabel($session->outcome),
        ];

        if ($session->primaryReactionClass?->label) {
            $lines[] = 'Главное возражение: '.$session->primaryReactionClass->label;
        }

        if (filled($session->notes)) {
            $lines[] = 'Комментарий: '.trim((string) $session->notes);
        }

        if ($fieldValues !== []) {
            $lines[] = '';
            $lines[] = 'Зафиксированные поля:';
            foreach ($fieldValues as $code => $value) {
                $lines[] = '- '.$this->fieldLabel($code).': '.$value;
            }
        }

        return Str::limit(trim(implode("\n", $lines)), 5000, '');
    }

    private function taskTitle(SalesScriptPlaySession $session): string
    {
        $scriptTitle = trim((string) ($session->version?->script?->title ?? 'скрипта'));

        return Str::limit('Следующий шаг после: '.$scriptTitle, 250, '');
    }

    private function taskPriority(?SalesPlaySessionOutcome $outcome): string
    {
        return match ($outcome) {
            SalesPlaySessionOutcome::Won,
            SalesPlaySessionOutcome::QuoteSent,
            SalesPlaySessionOutcome::Progress => 'high',
            default => 'medium',
        };
    }

    private function outcomeLabel(?SalesPlaySessionOutcome $outcome): string
    {
        return match ($outcome) {
            SalesPlaySessionOutcome::NoContact => 'нет контакта',
            SalesPlaySessionOutcome::Progress => 'прогресс',
            SalesPlaySessionOutcome::QuoteSent => 'КП / ставка отправлены',
            SalesPlaySessionOutcome::Won => 'успех',
            SalesPlaySessionOutcome::Lost => 'отказ',
            SalesPlaySessionOutcome::Postponed => 'отложено',
            null => '—',
        };
    }

    private function fieldLabel(string $code): string
    {
        return str_replace('_', ' ', $code);
    }
}
