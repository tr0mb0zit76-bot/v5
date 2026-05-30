<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderLeg;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\OrderClosingDocumentsNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderClosingDocumentsNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_clerk_receives_notification_when_transport_is_completed(): void
    {
        $clerk = $this->createClerkUser();
        $customer = Contractor::query()->create([
            'name' => 'ООО Клиент',
            'type' => 'customer',
        ]);

        $order = Order::factory()->create([
            'customer_id' => $customer->id,
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

        $this->assertTrue($service->isTransportCompleted($order->fresh(['legs.routePoints', 'documents', 'client'])));
        $this->assertTrue($service->maybeNotify($order->fresh(['legs.routePoints', 'documents', 'client'])));
        $this->assertSame(1, $clerk->fresh()->unreadNotifications()->count());

        $notification = $clerk->fresh()->unreadNotifications()->first();
        $this->assertSame('order_closing_documents_required', data_get($notification->data, 'kind'));
        $this->assertStringContainsString('ООО Клиент', (string) data_get($notification->data, 'body'));
        $this->assertStringContainsString('ORD-1001', (string) data_get($notification->data, 'body'));
        $this->assertStringContainsString('Москва - Санкт-Петербург', (string) data_get($notification->data, 'body'));
        $this->assertSame(route('orders.edit', [$order], false), data_get($notification->data, 'action_url'));

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

    private function createClerkUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'clerk',
            'display_name' => 'Делопроизводитель',
            'visibility_areas' => json_encode(['dashboard', 'orders', 'documents'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);
    }
}
