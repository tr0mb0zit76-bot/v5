<?php

namespace Tests\Unit;

use App\Http\Controllers\ActivityTimelineController;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ActivityTimelineControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'orders',
            'role_user',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->string('order_number')->nullable();
            $table->decimal('salary_accrued', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function test_order_timeline_rejects_non_admin(): void
    {
        $managerRole = Role::query()->create([
            'name' => 'manager',
            'visibility_areas' => ['orders'],
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
            'order_number' => 'AA-301',
        ]);

        $request = Request::create('/orders/'.$order->id.'/activity-timeline', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = app(ActivityTimelineController::class)->showForOrder($request, $order);

        $this->assertSame(200, $response->getStatusCode());
    }
}
