<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Order;
use App\Services\OneC\OneCCounterpartyEnsureService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Фоновая запись заказчика заказа в 1С (find-or-create), если его ещё нет.
 */
class EnsureOneCOrderCustomerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $orderId) {}

    public function handle(OneCCounterpartyEnsureService $ensure): void
    {
        if (! (bool) config('one_c.enabled', false)) {
            return;
        }

        $order = Order::query()->find($this->orderId);
        if ($order === null) {
            return;
        }

        $result = $ensure->ensureOrderCustomer($order);
        if ($result !== null) {
            Log::info('one_c.counterparty_ensured_for_order', [
                'order_id' => $this->orderId,
                'ref' => $result['ref'],
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        Log::warning('one_c.counterparty_ensure_job_failed', [
            'order_id' => $this->orderId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
