<?php

namespace App\Services\Disposition;

use App\Models\DispositionEntry;
use App\Models\Order;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Support\ActivityEventType;
use App\Support\DispositionSlot;
use Carbon\Carbon;

class DispositionActivityService
{
    public function __construct(
        private readonly ActivityLedgerService $activityLedger,
    ) {}

    public function recordCommentIfChanged(
        Order $order,
        DispositionEntry $entry,
        ?string $previousComment,
        User $user,
    ): void {
        $comment = trim((string) $entry->comment);

        if ($comment === '') {
            return;
        }

        if ($comment === trim((string) $previousComment)) {
            return;
        }

        $slot = DispositionSlot::tryFrom((string) $entry->slot);
        $dateLabel = $entry->date !== null
            ? Carbon::parse($entry->date)->format('d.m.Y')
            : '—';

        $slotLabel = $slot?->label() ?? (string) $entry->slot;

        $this->activityLedger->record(
            $order,
            ActivityEventType::DispositionComment,
            sprintf('Диспозиция: %s, %s', $slotLabel, $dateLabel),
            $comment,
            [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'date' => $entry->date?->toDateString(),
                'slot' => $entry->slot,
                'location' => $entry->location,
            ],
            $entry->recorded_at,
            $user,
            $entry,
        );
    }
}
