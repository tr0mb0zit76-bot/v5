<?php

namespace Tests\Feature\Orders;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderDocument;
use App\Models\OrderLeg;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\Orders\OrderRouteActualDateUpdateService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderRouteActualDateClosingNotificationTest extends TestCase
{
    public function test_clerk_receives_notification_when_unloading_actual_is_set_via_route_service(): void
    {
        $clerk = $this->createClerkUser();
        $actor = User::factory()->create(['email_verified_at' => now()]);

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
            'description' => 'leg_1',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'loading',
            'sequence' => 0,
            'address' => 'Москва',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'address' => 'Санкт-Петербург',
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

        app(OrderRouteActualDateUpdateService::class)->apply(
            $actor,
            $order->fresh(['legs.routePoints']),
            'unloading_actual',
            '2026-06-02',
        );

        $this->assertSame(1, $clerk->fresh()->unreadNotifications()->count());

        $notification = $clerk->fresh()->unreadNotifications()->first();
        $this->assertSame('order_closing_documents_required', data_get($notification->data, 'kind'));
        $this->assertStringContainsString('ORD-1001', (string) data_get($notification->data, 'body'));
    }

    public function test_route_service_does_not_notify_without_waybill(): void
    {
        $clerk = $this->createClerkUser();
        $actor = User::factory()->create(['email_verified_at' => now()]);

        $order = Order::factory()->create();

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
            'description' => 'leg_1',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 0,
        ]);

        app(OrderRouteActualDateUpdateService::class)->apply(
            $actor,
            $order->fresh(['legs.routePoints']),
            'unloading_actual',
            '2026-06-02',
        );

        $this->assertSame(0, $clerk->fresh()->unreadNotifications()->count());
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
