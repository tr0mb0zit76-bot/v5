<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderLeg;
use App\Models\Role;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\OrderClosingDocumentsNotificationService;
use Tests\TestCase;

class OrderClosingDocumentsNotificationTest extends TestCase
{
    public function test_clerk_receives_notification_when_transport_is_completed(): void
    {
        $clerk = $this->createClerkUser();
        $customer = Contractor::query()->create([
            'name' => 'ООО Клиент',
            'type' => 'customer',
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'company_code' => 'AA',
            'order_number' => 'ORD-1001',
            'order_date' => '2026-05-20',
        ]);

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'loading',
            'sequence' => 0,
            'address' => 'Москва',
            'actual_date' => '2026-05-24',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'address' => 'Санкт-Петербург',
            'actual_date' => '2026-05-25',
        ]);

        OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'waybill',
            'status' => 'signed',
            'original_name' => 'tn.pdf',
            'file_path' => 'orders/'.$order->id.'/tn.pdf',
            'metadata' => ['party' => 'internal', 'flow' => 'uploaded'],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        $service = app(OrderClosingDocumentsNotificationService::class);

        $orderForCheck = $order->fresh(['legs.routePoints', 'documents', 'client']);

        $this->assertTrue($service->isTransportCompleted($orderForCheck));
        $this->assertSame(1, $clerk->fresh()->unreadNotifications()->count());
        $this->assertFalse($service->maybeNotify($orderForCheck));

        $notification = $clerk->fresh()->unreadNotifications()->first();
        $this->assertSame('order_closing_documents_required', data_get($notification->data, 'kind'));
        $this->assertStringContainsString('ООО Клиент', (string) data_get($notification->data, 'body'));
        $this->assertStringContainsString('ORD-1001', (string) data_get($notification->data, 'body'));
        $this->assertStringContainsString('Москва - Санкт-Петербург', (string) data_get($notification->data, 'body'));
        $this->assertStringContainsString(
            'AA ООО Клиент заявка № ORD-1001',
            (string) data_get($notification->data, 'payload.clipboard_summary'),
        );
        $this->assertSame(route('orders.edit', [$order], false).'?tab=documents', data_get($notification->data, 'action_url'));

        $this->assertFalse($service->maybeNotify($order->fresh()));
    }

    public function test_notification_is_not_sent_without_waybill(): void
    {
        $this->createClerkUser();

        $order = Order::factory()->create();

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 0,
            'actual_date' => '2026-05-25',
        ]);

        $service = app(OrderClosingDocumentsNotificationService::class);

        $this->assertFalse($service->maybeNotify($order->fresh()));
    }

    public function test_clerk_receives_notification_when_waybill_is_uploaded_after_unload_date(): void
    {
        $clerk = $this->createClerkUser();

        $order = Order::factory()->create([
            'order_number' => 'ORD-2002',
        ]);

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 0,
            'actual_date' => '2026-06-01',
        ]);

        $service = app(OrderClosingDocumentsNotificationService::class);
        $this->assertFalse($service->maybeNotify($order->fresh(['legs.routePoints', 'documents'])));
        $this->assertSame(0, $clerk->fresh()->unreadNotifications()->count());

        OrderDocument::query()->create([
            'order_id' => $order->id,
            'type' => 'waybill',
            'status' => 'signed',
            'original_name' => 'tsd.pdf',
            'file_path' => 'orders/'.$order->id.'/tsd.pdf',
            'metadata' => ['party' => 'internal', 'flow' => 'uploaded'],
            'entity_type' => 'order',
            'entity_id' => $order->id,
        ]);

        $this->assertSame(1, $clerk->fresh()->unreadNotifications()->count());
        $this->assertSame(
            'order_closing_documents_required',
            data_get($clerk->fresh()->unreadNotifications()->first()->data, 'kind'),
        );
    }

    private function createClerkUser(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'clerk'],
            [
                'display_name' => 'Делопроизводитель',
                'visibility_areas' => ['dashboard', 'orders', 'documents'],
            ],
        );

        return User::factory()->create([
            'role_id' => $role->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }
}
