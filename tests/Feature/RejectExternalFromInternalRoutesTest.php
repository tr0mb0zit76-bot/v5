<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RejectExternalFromInternalRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_external_user_cannot_open_dashboard(): void
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            $this->markTestSkipped('is_external migration is not applied.');
        }

        $role = Role::query()->where('name', 'counterparty_carrier')->first();
        $this->assertNotNull($role);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_external' => true,
            'contractor_id' => null,
            'external_party' => 'carrier',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertRedirect(route('mobile.messenger.app'));
    }

    public function test_external_user_can_open_counterparty_orders_api(): void
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            $this->markTestSkipped('is_external migration is not applied.');
        }

        $role = Role::query()->where('name', 'counterparty_customer')->first();
        $this->assertNotNull($role);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'is_external' => true,
            'contractor_id' => null,
            'external_party' => 'customer',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->getJson(route('mobile.shell.counterparty.orders'))
            ->assertOk()
            ->assertJsonPath('orders', []);
    }
}
