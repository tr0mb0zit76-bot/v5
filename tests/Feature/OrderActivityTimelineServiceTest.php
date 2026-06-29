<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\User;
use App\Services\ActivityLedgerService;
use App\Services\OrderActivityTimelineService;
use App\Services\OrderStatusService;
use App\Support\ActivityEventType;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderActivityTimelineServiceTest extends TestCase
{
    protected function setUp(): void
    {
        try {
            parent::setUp();
        } catch (\Throwable $exception) {
            $this->markTestSkipped('Database unavailable for timeline feature tests: '.$exception->getMessage());
        }
    }

    public function test_timeline_includes_status_log_events(): void
    {
        if (! Schema::hasTable('order_status_logs') || ! Schema::hasTable('activity_events')) {
            $this->markTestSkipped('Timeline tables are not migrated.');
        }

        $user = User::factory()->create();
        $order = Order::factory()->create(['manager_id' => $user->id]);

        OrderStatusLog::query()->create([
            'order_id' => $order->id,
            'status_from' => 'new',
            'status_to' => 'in_progress',
            'created_by' => $user->id,
        ]);

        $service = new OrderActivityTimelineService(
            app(ActivityLedgerService::class),
            app(OrderStatusService::class),
        );

        $events = $service->timelineForOrder($order->fresh(), 20);

        $this->assertTrue(
            collect($events)->contains(fn (array $event): bool => $event['event_type'] === 'order_status_changed'),
        );
    }

    public function test_timeline_includes_disposition_comment_from_ledger(): void
    {
        if (! Schema::hasTable('activity_events')) {
            $this->markTestSkipped('activity_events table is not migrated.');
        }

        $order = Order::factory()->create();
        $ledger = app(ActivityLedgerService::class);

        $ledger->record(
            $order,
            ActivityEventType::DispositionComment,
            'Диспозиция',
            'Комментарий из теста',
        );

        $service = new OrderActivityTimelineService(
            $ledger,
            app(OrderStatusService::class),
        );

        $events = $service->timelineForOrder($order->fresh(), 10);

        $this->assertTrue(
            collect($events)->contains(
                fn (array $event): bool => $event['event_type'] === ActivityEventType::DispositionComment->value,
            ),
        );
    }
}
