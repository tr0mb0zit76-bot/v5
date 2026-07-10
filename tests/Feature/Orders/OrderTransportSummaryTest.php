<?php

namespace Tests\Feature\Orders;

use App\Models\Contractor;
use App\Models\FleetDriver;
use App\Models\FleetVehicle;
use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\RoutePoint;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderTransportSummaryTest extends TestCase
{
    public function test_transport_summary_endpoint_returns_clerk_format(): void
    {
        $user = $this->createManagerUser();

        $customer = Contractor::query()->create([
            'name' => 'ООО Клиент',
            'type' => 'customer',
        ]);

        $carrier = Contractor::query()->create([
            'name' => 'ООО Перевозчик',
            'type' => 'carrier',
        ]);

        $driverId = DB::table('drivers')->insertGetId([
            'first_name' => 'Пётр',
            'last_name' => 'Петров',
            'patronymic' => 'Петрович',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $order = Order::factory()->create([
            'manager_id' => $user->id,
            'customer_id' => $customer->id,
            'company_code' => 'AA',
            'order_number' => 'ORD-2002',
            'order_date' => '2026-05-21',
            'customer_rate' => 50000,
            'customer_payment_form' => 'no_vat',
            'driver_id' => $driverId,
        ]);

        if (Schema::hasColumn('orders', 'performers')) {
            $vehicle = FleetVehicle::query()->create([
                'owner_contractor_id' => $carrier->id,
                'tractor_brand' => 'Volvo',
                'tractor_plate' => 'К111КК77',
                'trailer_brand' => 'Krone',
                'trailer_plate' => 'Е222ЕЕ77',
            ]);

            $fleetDriver = FleetDriver::query()->create([
                'carrier_contractor_id' => $carrier->id,
                'full_name' => 'Петров Пётр Петрович',
            ]);

            $order->update([
                'performers' => [[
                    'fleet_driver_id' => $fleetDriver->id,
                    'fleet_vehicle_id' => $vehicle->id,
                ]],
            ]);
        }

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'loading',
            'sequence' => 0,
            'address' => 'Самара',
        ]);

        RoutePoint::factory()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'address' => 'Уфа',
        ]);

        $response = $this->actingAs($user)->getJson(route('orders.transport-summary', $order));

        $response->assertOk();
        $summary = (string) $response->json('summary');
        $this->assertStringContainsString('AA ООО Клиент заявка № ORD-2002 от 21.05.2026', $summary);
        $this->assertStringContainsString('50 000,00 руб.', $summary);
        $this->assertStringContainsString('Без НДС', $summary);
        $this->assertStringContainsString('маршрут Самара - Уфа', $summary);
        if (Schema::hasColumn('orders', 'performers')) {
            $this->assertStringContainsString('Volvo / К111КК77 / Krone / Е222ЕЕ77', $summary);
            $this->assertStringContainsString('Петров Пётр Петрович', $summary);
        } else {
            $this->assertStringContainsString('Петров Пётр', $summary);
        }
    }

    public function test_transport_summary_forbidden_for_foreign_order_with_own_scope(): void
    {
        if (! Schema::hasTable('orders')) {
            $this->markTestSkipped('orders table is unavailable.');
        }

        $owner = $this->createManagerUser('manager_owner');
        $intruder = $this->createManagerUser('manager_intruder');

        $order = Order::factory()->create([
            'manager_id' => $owner->id,
        ]);

        $this->actingAs($intruder)
            ->getJson(route('orders.transport-summary', $order))
            ->assertForbidden();
    }

    public function test_transport_summary_allowed_for_order_owner_with_own_scope(): void
    {
        if (! Schema::hasColumn('orders', 'order_owner_id')) {
            $this->markTestSkipped('orders.order_owner_id is unavailable.');
        }

        $owner = $this->createManagerUser('deal_owner');
        $dispatcherManager = $this->createManagerUser('dispatcher_manager');

        $order = Order::factory()->create([
            'manager_id' => $dispatcherManager->id,
            'order_owner_id' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->getJson(route('orders.transport-summary', $order))
            ->assertOk();
    }

    private function createManagerUser(string $roleName = 'manager'): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => $roleName,
            'display_name' => ucfirst(str_replace('_', ' ', $roleName)),
            'visibility_areas' => json_encode(['dashboard', 'orders'], JSON_THROW_ON_ERROR),
            'visibility_scopes' => json_encode(['orders' => 'own'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::factory()->create([
            'role_id' => $roleId,
            'email_verified_at' => now(),
        ]);
    }
}
