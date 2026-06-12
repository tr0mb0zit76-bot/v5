<?php

namespace Tests\Feature\Orders;

use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderBasedOnTemplateBuilder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderBasedOnTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany([
            'cargo_leg',
            'cargos',
            'route_points',
            'order_legs',
            'contractors',
            'orders',
            'users',
            'roles',
        ]);

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->json('visibility_scopes')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('contractors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('inn')->nullable();
            $table->string('type')->nullable();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('own_company_id')->nullable();
            $table->string('status')->nullable();
            $table->json('performers')->nullable();
            $table->timestamps();
        });

        Schema::create('order_legs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedInteger('sequence')->default(1);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('route_points', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_leg_id');
            $table->string('type');
            $table->unsignedInteger('sequence')->default(1);
            $table->string('address')->nullable();
            $table->json('normalized_data')->nullable();
            $table->timestamps();
        });

        Schema::create('cargos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('title')->nullable();
            $table->string('ati_cargo_name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('weight', 12, 3)->nullable();
            $table->decimal('volume', 12, 3)->nullable();
            $table->timestamps();
        });
    }

    public function test_builder_copies_customer_route_and_cargo_without_performers(): void
    {
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'visibility_scopes' => json_encode(['orders' => 'own'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = User::factory()->create();
        DB::table('users')->where('id', $manager->id)->update(['role_id' => $managerRoleId]);

        $customerId = DB::table('contractors')->insertGetId([
            'name' => 'ООО Клиент',
            'inn' => '7700000000',
            'type' => 'customer',
        ]);

        $orderId = DB::table('orders')->insertGetId([
            'order_number' => 'SRC-1',
            'manager_id' => $manager->id,
            'customer_id' => $customerId,
            'status' => 'in_progress',
            'performers' => json_encode([['fleet_vehicle_id' => 9, 'fleet_driver_id' => 8]], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $legId = DB::table('order_legs')->insertGetId([
            'order_id' => $orderId,
            'sequence' => 1,
            'description' => 'leg_1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('route_points')->insert([
            'order_leg_id' => $legId,
            'type' => 'loading',
            'sequence' => 1,
            'address' => 'Москва, склад 1',
            'normalized_data' => json_encode(['city' => 'Москва'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('cargos')->insert([
            'order_id' => $orderId,
            'title' => 'Бетон',
            'description' => 'М300',
            'weight' => 12000,
            'volume' => 45,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $template = app(OrderBasedOnTemplateBuilder::class)->build(Order::query()->with('client')->findOrFail($orderId));

        $this->assertSame($customerId, $template['client_id']);
        $this->assertSame([], $template['performers']);
        $this->assertCount(1, $template['route_points']);
        $this->assertSame('loading', $template['route_points'][0]['type']);
        $this->assertSame('Москва', $template['route_points'][0]['normalized_data']['city']);
        $this->assertCount(1, $template['cargo_items']);
        $this->assertSame('Бетон', $template['cargo_items'][0]['name']);
        $this->assertNull($template['financial_term']['client_price'] ?? null);
    }
}
