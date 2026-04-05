<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('orders');
        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable();
            $table->json('visibility_areas')->nullable();
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

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->date('order_date')->nullable();
            $table->string('customer_payment_form', 50)->nullable();
            $table->string('carrier_payment_form', 50)->nullable();
            $table->decimal('delta', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function test_dashboard_shows_current_user_period_metrics(): void
    {
        $managerRoleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['dashboard'], JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role_id' => $managerRoleId,
            'email_verified_at' => now(),
        ])->refresh();

        $otherUser = User::factory()->create([
            'role_id' => $managerRoleId,
            'email_verified_at' => now(),
        ])->refresh();

        DB::table('orders')->insert([
            [
                'manager_id' => $user->id,
                'order_date' => '2026-04-05',
                'customer_payment_form' => 'vat',
                'carrier_payment_form' => 'vat',
                'delta' => 15000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'manager_id' => $user->id,
                'order_date' => '2026-04-10',
                'customer_payment_form' => 'vat',
                'carrier_payment_form' => 'no_vat',
                'delta' => 5000,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'manager_id' => $user->id,
                'order_date' => '2026-03-30',
                'customer_payment_form' => 'vat',
                'carrier_payment_form' => 'vat',
                'delta' => 9999,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'manager_id' => $otherUser->id,
                'order_date' => '2026-04-08',
                'customer_payment_form' => 'vat',
                'carrier_payment_form' => 'vat',
                'delta' => 7777,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get(route('dashboard', [
            'date_from' => '2026-04-01',
            'date_to' => '2026-04-30',
        ]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('filters.date_from', '2026-04-01')
            ->where('filters.date_to', '2026-04-30')
            ->where('metrics.total_orders', 2)
            ->where('metrics.direct_orders', 1)
            ->where('metrics.direct_share_percent', 50)
            ->where('metrics.period_delta', 20000)
        );
    }
}
