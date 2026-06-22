<?php

namespace App\Services\Commercial;

use App\Enums\LeadCloseOutcomeFlag;
use App\Models\Lead;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Support\ActivityEventType;

final class LeadCloseOutcomeService
{
    public function __construct(
        private readonly ActivityLedgerService $activityLedger,
    ) {}

    /**
     * @param  list<string>|null  $secondaryFlags
     */
    public function apply(
        Lead $lead,
        LeadCloseOutcomeFlag $primaryFlag,
        ?User $user = null,
        ?string $note = null,
        ?array $secondaryFlags = null,
    ): void {
        $expectedOutcome = $primaryFlag->terminalOutcome();

        $lead->forceFill([
            'status' => $expectedOutcome,
            'close_outcome_primary_flag' => $primaryFlag->value,
            'close_outcome_secondary_flags' => $secondaryFlags ?? [],
            'lost_reason' => $note ?? $lead->lost_reason,
            'updated_by' => $user?->id ?? $lead->updated_by,
        ])->saveQuietly();

        $this->activityLedger->record(
            $lead,
            ActivityEventType::CloseOutcomeRecorded,
            'Причина закрытия',
            sprintf('%s: %s', $expectedOutcome === 'lost' ? 'Проигрыш' : 'Выигрыш', $primaryFlag->label()),
            [
                'primary_flag' => $primaryFlag->value,
                'primary_label' => $primaryFlag->label(),
                'terminal_outcome' => $expectedOutcome,
                'note' => $note,
                'secondary_flags' => $secondaryFlags ?? [],
            ],
            null,
            $user,
        );
    }

    /**
     * @return list<array{value: string, label: string, terminal_outcome: string}>
     */
    public static function optionsForUi(): array
    {
        return [
            ...array_map(fn (LeadCloseOutcomeFlag $flag): array => [
                'value' => $flag->value,
                'label' => $flag->label(),
                'terminal_outcome' => 'lost',
            ], LeadCloseOutcomeFlag::forLost()),
            ...array_map(fn (LeadCloseOutcomeFlag $flag): array => [
                'value' => $flag->value,
                'label' => $flag->label(),
                'terminal_outcome' => 'won',
            ], LeadCloseOutcomeFlag::forWon()),
        ];
    }
}
