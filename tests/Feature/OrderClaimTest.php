<?php

namespace Tests\Feature;

use App\Enums\OrderClaimParty;
use App\Enums\OrderClaimStatus;
use App\Enums\OrderClaimType;
use App\Models\ActivityEvent;
use App\Models\Order;
use App\Models\OrderClaim;
use App\Models\User;
use App\Support\ActivityEventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OrderClaimTest extends TestCase
{
    public function test_manager_can_create_claim_and_ledger_records_open(): void
    {
        if (! Schema::hasTable('order_claims')) {
            $this->markTestSkipped('Таблица order_claims недоступна.');
        }

        $manager = $this->makeManagerUser();
        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'ORD-CLAIM-1',
        ]);

        $response = $this->actingAs($manager)->post(route('orders.claims.store', $order), [
            'party' => OrderClaimParty::Customer->value,
            'type' => OrderClaimType::Late->value,
            'title' => 'Срыв срока выгрузки',
            'description' => 'Клиент фиксирует простой.',
            'amount_risk' => 12000,
            'currency' => 'RUB',
        ]);

        $response->assertRedirect(route('orders.edit', ['order' => $order->id, 'tab' => 'claims']));

        $claim = OrderClaim::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($claim);
        $this->assertSame(OrderClaimStatus::Open, $claim->status);
        $this->assertSame('Срыв срока выгрузки', $claim->title);
        $this->assertStringStartsWith('CL-', $claim->number);

        $this->assertTrue(
            ActivityEvent::query()
                ->where('subject_type', $order->getMorphClass())
                ->where('subject_id', $order->id)
                ->where('event_type', ActivityEventType::ClaimOpened->value)
                ->exists()
        );
    }

    public function test_manager_can_resolve_claim_and_ledger_records_closed(): void
    {
        if (! Schema::hasTable('order_claims')) {
            $this->markTestSkipped('Таблица order_claims недоступна.');
        }

        $manager = $this->makeManagerUser();
        $order = Order::factory()->create(['manager_id' => $manager->id]);
        $claim = OrderClaim::factory()->create([
            'order_id' => $order->id,
            'status' => OrderClaimStatus::Open,
            'responsible_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->patch(route('orders.claims.update', [$order, $claim]), [
            'status' => OrderClaimStatus::Resolved->value,
            'resolution_note' => 'Компенсация согласована',
        ]);

        $response->assertRedirect(route('orders.edit', ['order' => $order->id, 'tab' => 'claims']));

        $claim->refresh();
        $this->assertSame(OrderClaimStatus::Resolved, $claim->status);
        $this->assertNotNull($claim->resolved_at);

        $this->assertTrue(
            ActivityEvent::query()
                ->where('subject_type', $order->getMorphClass())
                ->where('subject_id', $order->id)
                ->where('event_type', ActivityEventType::ClaimClosed->value)
                ->exists()
        );
    }

    public function test_claims_index_lists_visible_claims(): void
    {
        if (! Schema::hasTable('order_claims')) {
            $this->markTestSkipped('Таблица order_claims недоступна.');
        }

        $manager = $this->makeManagerUser();
        $order = Order::factory()->create([
            'manager_id' => $manager->id,
            'order_number' => 'ORD-CLAIM-IDX',
        ]);
        OrderClaim::factory()->create([
            'order_id' => $order->id,
            'title' => 'Простой на погрузке',
            'responsible_id' => $manager->id,
            'created_by' => $manager->id,
        ]);

        $response = $this->actingAs($manager)->get(route('claims.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Claims/Index')
            ->has('claims', 1)
            ->where('claims.0.title', 'Простой на погрузке'));
    }

    private function makeManagerUser(): User
    {
        $roleId = DB::table('roles')->insertGetId([
            'name' => 'manager',
            'display_name' => 'Manager',
            'visibility_areas' => json_encode(['orders', 'claims']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return User::query()->create([
            'role_id' => $roleId,
            'name' => 'Claims Manager',
            'email' => 'claims-manager-'.uniqid('', true).'@example.test',
            'password' => bcrypt('secret'),
            'is_active' => true,
        ]);
    }
}
