<?php

namespace App\Services\Disposition;

use App\Models\DispositionEntry;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use App\Services\CabinetNotifier;
use App\Support\DispositionSlot;
use App\Support\TaskNumberGenerator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class DispositionReminderService
{
    public function __construct(
        private readonly TaskNumberGenerator $taskNumbers,
        private readonly CabinetNotifier $cabinetNotifier,
        private readonly DispositionInProgressOrderScope $orderScope,
    ) {}

    public function slotKey(int $orderId, string $date, string $slot): string
    {
        return "{$orderId}|{$date}|{$slot}";
    }

    public function isSlotFilled(?DispositionEntry $entry): bool
    {
        if ($entry === null) {
            return false;
        }

        return trim((string) $entry->location) !== '';
    }

    /**
     * Создаёт задачи менеджерам по заказам без заполненного слота за указанную дату.
     */
    public function createRemindersForSlot(DispositionSlot $slot, ?Carbon $date = null): int
    {
        if (! config('disposition.reminder_tasks_enabled', false)) {
            return 0;
        }

        if (! Schema::hasTable('tasks') || ! Schema::hasTable('disposition_entries')) {
            return 0;
        }

        $date = ($date ?? Carbon::today())->toDateString();
        $created = 0;

        $columns = ['id', 'order_number', 'manager_id', 'loading_date', 'unloading_date'];
        if (Schema::hasColumn('orders', 'performers')) {
            $columns[] = 'performers';
        }

        $orders = Order::query()
            ->whereNotNull('manager_id')
            ->when(Schema::hasColumn('orders', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->get($columns);

        $inTransitIds = $this->orderScope->filterInTransit($orders);
        $orders = $orders->filter(fn (Order $order): bool => in_array($order->id, $inTransitIds, true))->values();

        $orderIds = $orders->pluck('id')->all();

        $entries = DispositionEntry::query()
            ->whereIn('order_id', $orderIds)
            ->where('date', $date)
            ->where('slot', $slot->value)
            ->get()
            ->keyBy('order_id');

        foreach ($orders as $order) {
            $entry = $entries->get($order->id);

            if ($this->isSlotFilled($entry)) {
                continue;
            }

            $metaKey = $this->slotKey($order->id, $date, $slot->value);

            $exists = Task::query()
                ->where('order_id', $order->id)
                ->where('meta->disposition_slot_key', $metaKey)
                ->where('status', '!=', 'done')
                ->exists();

            if ($exists) {
                continue;
            }

            $managerId = (int) $order->manager_id;

            if ($managerId <= 0) {
                continue;
            }

            $task = Task::query()->create([
                'number' => $this->taskNumbers->next(),
                'title' => $this->reminderTitle($order->order_number, $slot, $date),
                'description' => $this->reminderDescription($order->order_number, $slot, $date),
                'status' => 'new',
                'priority' => 'high',
                'due_at' => now()->endOfDay(),
                'responsible_id' => $managerId,
                'order_id' => $order->id,
                'created_by' => null,
                'meta' => [
                    'disposition_slot_key' => $metaKey,
                    'disposition_date' => $date,
                    'disposition_slot' => $slot->value,
                ],
            ]);

            $manager = User::query()->find($managerId);
            if ($manager !== null) {
                $this->cabinetNotifier->notifyTaskAssigned($task, null);
            }

            $created++;
        }

        return $created;
    }

    public function closeRemindersForFilledSlot(int $orderId, string $date, string $slot): int
    {
        if (! Schema::hasTable('tasks')) {
            return 0;
        }

        $metaKey = $this->slotKey($orderId, $date, $slot);

        return Task::query()
            ->where('meta->disposition_slot_key', $metaKey)
            ->where('status', '!=', 'done')
            ->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);
    }

    private function reminderTitle(?string $orderNumber, DispositionSlot $slot, string $date): string
    {
        $label = Carbon::parse($date)->format('d.m.Y');

        return sprintf(
            'Диспозиция: %s — %s (%s)',
            $slot->label(),
            $orderNumber ?: 'заказ',
            $label,
        );
    }

    private function reminderDescription(?string $orderNumber, DispositionSlot $slot, string $date): string
    {
        return sprintf(
            'Заполните в гриде «Диспозиция» местоположение за %s (%s) по заказу %s.',
            $slot->label(),
            Carbon::parse($date)->format('d.m.Y'),
            $orderNumber ?: '#',
        );
    }
}
