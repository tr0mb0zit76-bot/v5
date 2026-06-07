<?php

namespace App\Services\Disposition;

use App\Models\DispositionEntry;
use App\Models\User;
use App\Support\DispositionSlot;
use App\Support\RoleAccess;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class DispositionKpiService
{
    public function __construct(
        private readonly DispositionInProgressOrderScope $orderScope,
        private readonly DispositionReminderService $reminders,
    ) {}

    /**
     * KPI за календарный день (по умолчанию — сегодня в TZ диспозиции).
     *
     * @return array{
     *     date: string,
     *     orders_in_progress: int,
     *     both_slots_filled_count: int,
     *     both_slots_fill_percent: float,
     *     morning_filled_count: int,
     *     evening_filled_count: int,
     *     morning_fill_percent: float,
     *     evening_fill_percent: float,
     *     avg_delay_minutes: ?float,
     *     delayed_entries_count: int,
     *     filled_entries_count: int
     * }
     */
    public function metricsForUser(User $user, ?string $date = null, bool $onlyOwnManagerOrders = false): array
    {
        if (! Schema::hasTable('disposition_entries')) {
            return $this->emptyMetrics($date);
        }

        $timezone = (string) config('disposition.timezone', 'Europe/Samara');
        $dateString = $date ?? Carbon::now($timezone)->toDateString();

        $orderIds = $onlyOwnManagerOrders
            ? $this->orderScope->orderIdsForManager($user->id)
            : $this->orderScope->orderIdsForUser($user);
        $totalOrders = count($orderIds);

        if ($totalOrders === 0) {
            return $this->emptyMetrics($dateString);
        }

        /** @var Collection<int, Collection<int, DispositionEntry>> $entriesByOrder */
        $entriesByOrder = DispositionEntry::query()
            ->whereIn('order_id', $orderIds)
            ->where('date', $dateString)
            ->get()
            ->groupBy('order_id');

        $bothFilled = 0;
        $morningFilled = 0;
        $eveningFilled = 0;

        foreach ($orderIds as $orderId) {
            $orderEntries = $entriesByOrder->get($orderId) ?? collect();
            $morning = $orderEntries->first(fn (DispositionEntry $entry): bool => $entry->slot === DispositionSlot::Morning->value);
            $evening = $orderEntries->first(fn (DispositionEntry $entry): bool => $entry->slot === DispositionSlot::Evening->value);

            if ($this->reminders->isSlotFilled($morning)) {
                $morningFilled++;
            }

            if ($this->reminders->isSlotFilled($evening)) {
                $eveningFilled++;
            }

            if ($this->reminders->isSlotFilled($morning) && $this->reminders->isSlotFilled($evening)) {
                $bothFilled++;
            }
        }

        $delayStats = $this->delayStatsForDate($dateString, $orderIds, $timezone);

        return [
            'date' => $dateString,
            'orders_in_progress' => $totalOrders,
            'both_slots_filled_count' => $bothFilled,
            'both_slots_fill_percent' => $this->percent($bothFilled, $totalOrders),
            'morning_filled_count' => $morningFilled,
            'evening_filled_count' => $eveningFilled,
            'morning_fill_percent' => $this->percent($morningFilled, $totalOrders),
            'evening_fill_percent' => $this->percent($eveningFilled, $totalOrders),
            'avg_delay_minutes' => $delayStats['avg_delay_minutes'],
            'delayed_entries_count' => $delayStats['delayed_entries_count'],
            'filled_entries_count' => $delayStats['filled_entries_count'],
        ];
    }

    public function userSeesDashboardWidget(User $user): bool
    {
        if (! Schema::hasTable('disposition_entries')) {
            return false;
        }

        $user->loadMissing('role');

        if (! RoleAccess::canAccessVisibilityArea($user, 'orders')) {
            return false;
        }

        $roleName = (string) ($user->role?->name ?? '');

        return in_array($roleName, ['admin', 'supervisor'], true);
    }

    /**
     * @param  list<int>  $orderIds
     * @return array{
     *     avg_delay_minutes: ?float,
     *     delayed_entries_count: int,
     *     filled_entries_count: int
     * }
     */
    private function delayStatsForDate(string $date, array $orderIds, string $timezone): array
    {
        $entries = DispositionEntry::query()
            ->whereIn('order_id', $orderIds)
            ->where('date', $date)
            ->whereNotNull('recorded_at')
            ->get();

        $delays = [];
        $delayedCount = 0;

        foreach ($entries as $entry) {
            if (! $this->reminders->isSlotFilled($entry)) {
                continue;
            }

            $deadline = $this->slotDeadline($date, (string) $entry->slot, $timezone);
            $recordedAt = $entry->recorded_at?->copy()->timezone($timezone);

            if ($deadline === null || $recordedAt === null) {
                continue;
            }

            $delayMinutes = $recordedAt->greaterThan($deadline)
                ? (float) $deadline->diffInMinutes($recordedAt)
                : 0.0;

            $delays[] = $delayMinutes;

            if ($delayMinutes > 0) {
                $delayedCount++;
            }
        }

        if ($delays === []) {
            return [
                'avg_delay_minutes' => null,
                'delayed_entries_count' => 0,
                'filled_entries_count' => 0,
            ];
        }

        return [
            'avg_delay_minutes' => round(array_sum($delays) / count($delays), 1),
            'delayed_entries_count' => $delayedCount,
            'filled_entries_count' => count($delays),
        ];
    }

    private function slotDeadline(string $date, string $slot, string $timezone): ?Carbon
    {
        $schedule = config('disposition.reminder_schedule', []);
        $time = $schedule[$slot] ?? null;

        if (! is_string($time) || $time === '') {
            return null;
        }

        return Carbon::parse("{$date} {$time}", $timezone);
    }

    /**
     * @return array{
     *     date: string,
     *     orders_in_progress: int,
     *     both_slots_filled_count: int,
     *     both_slots_fill_percent: float,
     *     morning_filled_count: int,
     *     evening_filled_count: int,
     *     morning_fill_percent: float,
     *     evening_fill_percent: float,
     *     avg_delay_minutes: null,
     *     delayed_entries_count: int,
     *     filled_entries_count: int
     * }
     */
    private function emptyMetrics(?string $date): array
    {
        $timezone = (string) config('disposition.timezone', 'Europe/Samara');
        $dateString = $date ?? Carbon::now($timezone)->toDateString();

        return [
            'date' => $dateString,
            'orders_in_progress' => 0,
            'both_slots_filled_count' => 0,
            'both_slots_fill_percent' => 0.0,
            'morning_filled_count' => 0,
            'evening_filled_count' => 0,
            'morning_fill_percent' => 0.0,
            'evening_fill_percent' => 0.0,
            'avg_delay_minutes' => null,
            'delayed_entries_count' => 0,
            'filled_entries_count' => 0,
        ];
    }

    private function percent(int $part, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round(($part / $total) * 100, 1);
    }
}
