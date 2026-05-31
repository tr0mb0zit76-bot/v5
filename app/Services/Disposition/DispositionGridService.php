<?php

namespace App\Services\Disposition;

use App\Models\DispositionEntry;
use App\Models\Order;
use App\Models\User;
use App\Support\DispositionSlot;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DispositionGridService
{
    public function __construct(
        private readonly DispositionReminderService $reminders,
        private readonly DispositionActivityService $activity,
    ) {}

    private const int DEFAULT_PAST_DAYS = 14;

    private const int DEFAULT_FUTURE_DAYS = 21;

    private const string IN_PROGRESS_STATUS = 'in_progress';

    /**
     * @return array{
     *     dates: list<string>,
     *     today: string,
     *     rows: list<array<string, mixed>>,
     *     status_filter: string
     * }
     */
    public function buildGridPayload(User $user): array
    {
        $orders = $this->inProgressOrdersQuery($user)->get();
        $dates = $this->resolveDateRange($orders);
        $entries = $this->loadEntries($orders->pluck('id')->all(), $dates);

        $today = Carbon::today()->toDateString();

        return [
            'dates' => $dates,
            'today' => $today,
            'status_filter' => self::IN_PROGRESS_STATUS,
            'rows' => $orders->map(fn (Order $order): array => $this->serializeRow($order, $dates, $entries))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function upsertCell(
        User $user,
        int $orderId,
        string $date,
        string $slot,
        ?string $location,
        ?string $comment,
    ): array {
        $this->ensureCanAccessOrder($user, $orderId);

        $slotEnum = DispositionSlot::from($slot);
        $parsedDate = Carbon::parse($date)->toDateString();

        $previous = DispositionEntry::query()
            ->where('order_id', $orderId)
            ->where('date', $parsedDate)
            ->where('slot', $slotEnum->value)
            ->first();

        $previousComment = $previous?->comment;

        $entry = $previous ?? new DispositionEntry([
            'order_id' => $orderId,
            'date' => $parsedDate,
            'slot' => $slotEnum->value,
        ]);

        if ($location !== null) {
            $entry->location = $this->nullableTrim($location);
        }

        if ($comment !== null) {
            $entry->comment = $this->nullableTrim($comment);
        }

        $entry->recorded_at = now();
        $entry->recorded_by = $user->id;
        $entry->save();

        $order = Order::query()->findOrFail($orderId);
        $this->activity->recordCommentIfChanged($order, $entry, $previousComment, $user);

        if ($this->reminders->isSlotFilled($entry)) {
            $this->reminders->closeRemindersForFilledSlot($orderId, $parsedDate, $slotEnum->value);
        }

        return [
            'entry' => [
                'id' => $entry->id,
                'order_id' => $entry->order_id,
                'date' => $entry->date?->toDateString(),
                'slot' => $entry->slot,
                'location' => $entry->location,
                'comment' => $entry->comment,
                'recorded_at' => $entry->recorded_at?->toIso8601String(),
                'recorded_by' => $entry->recorded_by,
            ],
        ];
    }

    /**
     * @return Builder<Order>
     */
    private function inProgressOrdersQuery(User $user): Builder
    {
        $builder = Order::query()
            ->with([
                'client:id,name',
                'carrier:id,name',
            ])
            ->whereRaw('COALESCE(manual_status, status) = ?', [self::IN_PROGRESS_STATUS])
            ->orderBy('order_number')
            ->orderBy('id');

        if (Schema::hasColumn('orders', 'deleted_at')) {
            $builder->whereNull('deleted_at');
        }

        if (! RoleAccess::isAdminUser($user)) {
            $scope = RoleAccess::resolveVisibilityScopeForUser($user, 'orders');

            if ($scope !== 'all') {
                $builder->where('manager_id', $user->id);
            }
        }

        return $builder;
    }

    private function ensureCanAccessOrder(User $user, int $orderId): void
    {
        $exists = $this->inProgressOrdersQuery($user)->whereKey($orderId)->exists();

        if (! $exists) {
            abort(403, 'Заказ недоступен или не в статусе «Выполняется».');
        }
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<string>
     */
    private function resolveDateRange(Collection $orders): array
    {
        $today = Carbon::today();
        $start = $today->copy()->subDays(self::DEFAULT_PAST_DAYS);
        $end = $today->copy()->addDays(self::DEFAULT_FUTURE_DAYS);

        foreach ($orders as $order) {
            if ($order->loading_date !== null) {
                $loading = Carbon::parse($order->loading_date)->startOfDay();
                if ($loading->lt($start)) {
                    $start = $loading->copy();
                }
            }

            if ($order->unloading_date !== null) {
                $unloading = Carbon::parse($order->unloading_date)->startOfDay();
                if ($unloading->gt($end)) {
                    $end = $unloading->copy();
                }
            }
        }

        if ($start->gt($today)) {
            $start = $today->copy();
        }

        $dates = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param  list<int>  $orderIds
     * @param  list<string>  $dates
     * @return Collection<string, DispositionEntry>
     */
    private function loadEntries(array $orderIds, array $dates): Collection
    {
        if ($orderIds === [] || $dates === []) {
            return collect();
        }

        return DispositionEntry::query()
            ->whereIn('order_id', $orderIds)
            ->whereBetween('date', [min($dates), max($dates)])
            ->get()
            ->keyBy(fn (DispositionEntry $entry): string => $this->cellKey(
                (int) $entry->order_id,
                $entry->date?->toDateString() ?? '',
                $entry->slot,
            ));
    }

    /**
     * @param  list<string>  $dates
     * @param  Collection<string, DispositionEntry>  $entries
     * @return array<string, mixed>
     */
    private function serializeRow(Order $order, array $dates, Collection $entries): array
    {
        $row = [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->client?->name,
            'carrier_name' => $order->carrier?->name,
            'planned_arrival_date' => $order->unloading_date?->toDateString(),
            'route_hint' => $this->routeHint($order),
        ];

        foreach ($dates as $date) {
            foreach (DispositionSlot::cases() as $slot) {
                $entry = $entries->get($this->cellKey($order->id, $date, $slot->value));

                $prefix = $this->fieldPrefix($date, $slot->value);
                $row[$prefix.'_location'] = $entry?->location;
                $row[$prefix.'_comment'] = $entry?->comment;
                $row[$prefix.'_entry_id'] = $entry?->id;
                $row[$prefix.'_recorded_at'] = $entry?->recorded_at?->toIso8601String();
            }
        }

        return $row;
    }

    private function routeHint(Order $order): string
    {
        $parts = array_filter([
            $order->client?->name,
            $order->carrier?->name,
        ]);

        return implode(' → ', $parts) ?: '—';
    }

    private function cellKey(int $orderId, string $date, string $slot): string
    {
        return "{$orderId}|{$date}|{$slot}";
    }

    private function fieldPrefix(string $date, string $slot): string
    {
        return 'cell_'.str_replace('-', '', $date).'_'.$slot;
    }

    private function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
