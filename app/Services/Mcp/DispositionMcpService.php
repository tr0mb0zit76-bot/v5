<?php

namespace App\Services\Mcp;

use App\Models\User;
use App\Services\Disposition\DispositionGridService;
use App\Support\DispositionSlot;
use Illuminate\Validation\ValidationException;

final class DispositionMcpService
{
    public function __construct(
        private readonly McpAccessGate $access,
        private readonly DispositionGridService $grid,
    ) {}

    /**
     * @return array{entry: array<string, mixed>}
     */
    public function upsertEntry(
        User $user,
        int $orderId,
        string $date,
        string $slot,
        ?string $location,
        ?string $comment,
    ): array {
        $this->access->requireOrdersArea($user);
        $this->access->findAccessibleOrder($user, $orderId);

        if (! in_array($slot, DispositionSlot::values(), true)) {
            throw ValidationException::withMessages([
                'slot' => 'Слот должен быть morning или evening.',
            ]);
        }

        if ($location === null && $comment === null) {
            throw ValidationException::withMessages([
                'location' => 'Укажите location и/или comment.',
            ]);
        }

        $result = $this->grid->upsertCell(
            $user,
            $orderId,
            $date,
            $slot,
            $location,
            $comment,
        );

        return $result;
    }
}
