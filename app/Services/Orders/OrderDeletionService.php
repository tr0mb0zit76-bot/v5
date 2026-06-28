<?php

namespace App\Services\Orders;

use App\Models\Cargo;
use App\Models\Order;
use App\Support\UserFacingDatabaseMessageResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class OrderDeletionService
{
    public function __construct(
        private readonly UserFacingDatabaseMessageResolver $databaseMessages,
    ) {}

    public function delete(Order $order, callable $loadOrderForEditing): void
    {
        try {
            DB::transaction(function () use ($order, $loadOrderForEditing): void {
                $order = $loadOrderForEditing($order);
                $this->purgeRelatedRecords($order);
                $order->delete();
            });
        } catch (QueryException $exception) {
            $message = $this->databaseMessages->resolve($exception)
                ?? 'Не удалось удалить заказ. Проверьте связанные документы и платежи.';

            throw ValidationException::withMessages([
                'order' => $message,
            ]);
        }
    }

    private function purgeRelatedRecords(Order $order): void
    {
        $cargoItems = $order->relationLoaded('cargoItems')
            ? $order->cargoItems
            : $order->cargoItems()->get();

        DB::table('cargo_leg')
            ->when(
                $cargoItems->isNotEmpty(),
                fn ($query) => $query->whereIn('cargo_id', $cargoItems->pluck('id')),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->delete();

        $legIds = $order->legs->pluck('id');

        if ($legIds->isNotEmpty()) {
            if (Schema::hasTable('route_points')) {
                DB::table('route_points')->whereIn('order_leg_id', $legIds)->delete();
            }

            if (Schema::hasTable('leg_costs')) {
                DB::table('leg_costs')->whereIn('order_leg_id', $legIds)->delete();
            }

            if (Schema::hasTable('leg_contractor_assignments')) {
                DB::table('leg_contractor_assignments')->whereIn('order_leg_id', $legIds)->delete();
            }
        }

        $this->deleteByOrderId('disposition_entries', $order->id);
        $this->deleteByOrderId('fleet_trips', $order->id);
        $this->deleteByOrderId('order_intake_drafts', $order->id);
        $this->deleteByOrderId('order_portal_invites', $order->id);
        $this->deleteByOrderId('finance_documents', $order->id);
        $this->deleteByOrderId('payment_schedule_payment_events', $order->id);
        $this->deleteByOrderId('payment_schedules', $order->id);

        if (Schema::hasTable('order_documents')) {
            $order->documents()->delete();
        }

        if (Schema::hasTable('financial_terms')) {
            $order->financialTerms()->delete();
        }

        if (Schema::hasTable('order_status_logs')) {
            $order->statusLogs()->delete();
        }

        if (Schema::hasTable('tasks') && Schema::hasColumn('tasks', 'order_id')) {
            DB::table('tasks')->where('order_id', $order->id)->delete();
        }

        if (Schema::hasTable('mail_threads') && Schema::hasColumn('mail_threads', 'order_id')) {
            $threadIds = DB::table('mail_threads')->where('order_id', $order->id)->pluck('id');

            if ($threadIds->isNotEmpty() && Schema::hasTable('mail_messages')) {
                DB::table('mail_messages')->whereIn('mail_thread_id', $threadIds)->delete();
            }

            DB::table('mail_threads')->where('order_id', $order->id)->delete();
        }

        if (Schema::hasTable('activity_events')) {
            DB::table('activity_events')
                ->where('subject_type', Order::class)
                ->where('subject_id', $order->id)
                ->delete();
        }

        if (Schema::hasColumn('cargos', 'order_id')) {
            $order->cargoItems()->delete();
        } elseif ($cargoItems->isNotEmpty()) {
            Cargo::query()->whereIn('id', $cargoItems->pluck('id'))->delete();
        }

        if (Schema::hasTable('order_legs')) {
            DB::table('order_legs')->where('order_id', $order->id)->delete();
        }
    }

    private function deleteByOrderId(string $table, int $orderId): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'order_id')) {
            return;
        }

        DB::table($table)->where('order_id', $orderId)->delete();
    }
}
