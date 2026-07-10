<?php

namespace Tests\Unit;

use App\Http\Controllers\ActivityTimelineController;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActivityTimelineControllerTest extends TestCase
{
    public function test_order_timeline_allows_manager_for_own_order(): void
    {
        $managerRole = Role::query()->create([
            'name' => 'manager',
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'own'],
        ]);
        $manager = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => bcrypt('secret'),
            'role_id' => $managerRole->id,
        ]);

        $order = Order::query()->create([
            'order_number' => 'AA-300',
            'manager_id' => $manager->id,
        ]);

        $request = Request::create('/orders/'.$order->id.'/activity-timeline', 'GET');
        $request->setUserResolver(fn () => $manager);

        $response = app(ActivityTimelineController::class)->showForOrder($request, $order);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_order_timeline_rejects_foreign_order_for_manager(): void
    {
        $managerRole = Role::query()->create([
            'name' => 'manager',
            'visibility_areas' => ['orders'],
            'visibility_scopes' => ['orders' => 'own'],
        ]);
        $manager = User::query()->create([
            'name' => 'Manager',
            'email' => 'manager@example.com',
            'password' => bcrypt('secret'),
            'role_id' => $managerRole->id,
        ]);
        $foreignManager = User::query()->create([
            'name' => 'Foreign',
            'email' => 'foreign@example.com',
            'password' => bcrypt('secret'),
            'role_id' => $managerRole->id,
        ]);

        $order = Order::query()->create([
            'order_number' => 'AA-301',
            'manager_id' => $foreignManager->id,
        ]);

        $request = Request::create('/orders/'.$order->id.'/activity-timeline', 'GET');
        $request->setUserResolver(fn () => $manager);

        $this->expectException(HttpException::class);

        app(ActivityTimelineController::class)->showForOrder($request, $order);
    }

    public function test_order_timeline_allows_admin(): void
    {
        $adminRole = Role::query()->create(['name' => 'admin']);
        $admin = User::query()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('secret'),
            'role_id' => $adminRole->id,
        ]);

        $order = Order::query()->create([
            'order_number' => 'AA-302',
        ]);

        $request = Request::create('/orders/'.$order->id.'/activity-timeline', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = app(ActivityTimelineController::class)->showForOrder($request, $order);

        $this->assertSame(200, $response->getStatusCode());
    }
}
