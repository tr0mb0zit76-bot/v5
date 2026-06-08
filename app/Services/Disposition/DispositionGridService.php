<?php

namespace App\Services\Disposition;

use App\Models\DispositionEntry;
use App\Models\Order;
use App\Models\User;
use App\Support\DispositionSlot;
use App\Support\DispositionUnclosedTrip;
use App\Support\OrderTransportTypeResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DispositionGridService
{
    public function __construct(
        private readonly DispositionReminderService $reminders,
        private readonly DispositionActivityService $activity,
        private readonly OrderTransportTypeResolver $transportTypes,
        private readonly DispositionInProgressOrderScope $orderScope,
    ) {}

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
        $inTransitIds = $this->orderScope->filterInTransit($orders);
        $orders = $orders
            ->filter(fn (Order $order): bool => in_array($order->id, $inTransitIds, true))
            ->values();

        $today = Carbon::today()->toDateString();
        $dates = $this->resolveDateRange($orders, $today);
        $entries = $this->loadEntries($orders->pluck('id')->all(), $dates);

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
            ->select('orders.*')
            ->selectSub($this->routePointSubquery('loading'), 'loading_point')
            ->selectSub($this->routePointSubquery('unloading', last: true), 'unloading_point')
            ->with([
                'client:id,name',
            ])
            ->orderBy('order_number')
            ->orderBy('id');

        $this->orderScope->apply($builder, $user);

        return $builder;
    }

    private function ensureCanAccessOrder(User $user, int $orderId): void
    {
        $order = $this->inProgressOrdersQuery($user)->whereKey($orderId)->first();

        if ($order === null) {
            abort(403, 'Заказ недоступен или не «в пути» (между фактической погрузкой и выгрузкой).');
        }

        if (! in_array($orderId, $this->orderScope->filterInTransit(collect([$order])), true)) {
            abort(403, 'Заказ недоступен или не «в пути» (между фактической погрузкой и выгрузкой).');
        }
    }

    /**
     * @param  Collection<int, Order>  $orders
     * @return list<string>
     */
    private function resolveDateRange(Collection $orders, string $today): array
    {
        $unclosed = $orders->filter(
            fn (Order $order): bool => DispositionUnclosedTrip::isUnclosed($order, $today),
        );
        $ordersForStart = $unclosed->isNotEmpty() ? $unclosed : $orders;

        $start = null;
        $end = null;

        foreach ($ordersForStart as $order) {
            if ($order->loading_date !== null) {
                $loading = Carbon::parse($order->loading_date)->startOfDay();
                $start = $start === null || $loading->lt($start) ? $loading : $start;
            }
        }

        foreach ($orders as $order) {
            if ($order->unloading_date !== null) {
                $unloading = Carbon::parse($order->unloading_date)->startOfDay();
                $end = $end === null || $unloading->gt($end) ? $unloading : $end;
            }
        }

        if ($start === null && $end === null) {
            return [];
        }

        if ($start === null) {
            $start = $end->copy();
        }

        if ($end === null) {
            $end = $start->copy();
        }

        if ($start->gt($end)) {
            $end = $start->copy();
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
            'route_label' => $this->routeLabel($order),
            'transport_type_label' => $this->transportTypes->labelForOrder($order),
            'planned_arrival_date' => $order->unloading_date?->toDateString(),
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

    private function routeLabel(Order $order): string
    {
        $loading = $this->nullableTrim($order->getAttribute('loading_point'));
        $unloading = $this->nullableTrim($order->getAttribute('unloading_point'));

        return ($loading ?? '—').' → '.($unloading ?? '—');
    }

    private function routePointSubquery(string $type, bool $last = false)
    {
        $cityCandidates = array_values(array_filter([
            Schema::hasColumn('route_points', 'normalized_data')
                ? "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(route_points.normalized_data, '$.city')), '')"
                : null,
            Schema::hasColumn('route_points', 'metadata')
                ? "NULLIF(JSON_UNQUOTE(JSON_EXTRACT(route_points.metadata, '$.normalized_data.city')), '')"
                : null,
            'NULLIF(cities.name, "")',
        ]));
        $cityExpression = match (count($cityCandidates)) {
            0 => 'NULL',
            1 => $cityCandidates[0],
            default => 'COALESCE('.implode(', ', $cityCandidates).')',
        };

        $addressExpression = Schema::hasColumn('route_points', 'address')
            ? 'COALESCE(NULLIF(route_points.address, ""), NULLIF(cities.name, ""), addresses.address_line)'
            : 'COALESCE(NULLIF(cities.name, ""), addresses.address_line)';
        $displayExpression = "COALESCE({$cityExpression}, {$addressExpression})";

        $query = DB::table('route_points')
            ->join('order_legs', 'order_legs.id', '=', 'route_points.order_leg_id')
            ->leftJoin('addresses', 'addresses.id', '=', 'route_points.address_id')
            ->leftJoin('cities', 'cities.id', '=', 'addresses.city_id')
            ->selectRaw($displayExpression)
            ->whereColumn('order_legs.order_id', 'orders.id')
            ->where('route_points.type', $type);

        if ($last) {
            return $query
                ->orderByDesc('order_legs.sequence')
                ->orderByDesc('route_points.sequence')
                ->limit(1);
        }

        return $query
            ->orderBy('order_legs.sequence')
            ->orderBy('route_points.sequence')
            ->limit(1);
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
