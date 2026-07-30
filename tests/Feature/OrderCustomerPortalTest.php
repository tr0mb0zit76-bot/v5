<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\Order;
use App\Models\OrderLeg;
use App\Models\OrderPortalInvite;
use App\Models\RoutePoint;
use App\Models\User;
use App\Services\OrderCustomerPortalDocumentService;
use App\Services\OrderPortalInviteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderCustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_customer_portal_invite(): void
    {
        if (! Schema::hasTable('order_portal_invites')) {
            $this->markTestSkipped('order_portal_invites migration is not applied.');
        }

        config(['portal.invite_ttl_days' => 90]);

        $staff = $this->createManagerUser();
        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Заказчик',
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-CUST-01',
            'company_code' => 'ORD',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_active' => true,
            'customer_id' => $customer->id,
            'manager_id' => $staff->id,
        ]);

        $this->actingAs($staff)->postJson(route('orders.portal-invites.customer.store', $order))
            ->assertOk()
            ->assertJsonStructure(['url', 'invite_id']);

        $this->assertDatabaseHas('order_portal_invites', [
            'order_id' => $order->id,
            'contractor_id' => $customer->id,
            'purpose' => OrderPortalInvite::PURPOSE_CUSTOMER_DOCUMENTS,
            'stage' => 'customer',
        ]);

        $invite = OrderPortalInvite::query()->where('order_id', $order->id)->sole();
        $this->assertSame(90, (int) config('portal.invite_ttl_days'));
        $this->assertTrue($invite->expires_at->between(now()->addDays(89), now()->addDays(91)));
    }

    public function test_customer_portal_page_renders_for_valid_token(): void
    {
        if (! Schema::hasTable('order_portal_invites')) {
            $this->markTestSkipped('order_portal_invites migration is not applied.');
        }

        [$invite, $token] = $this->createCustomerInvite();

        $this->get(route('portal.customer.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/CustomerDocuments')
                ->where('status', 'open')
                ->where('customer.name', 'ООО Заказчик')
                ->where('trip_status.code', 'draft')
                ->where('order.status_label', 'Новый заказ')
                ->has('route_milestones'));
    }

    public function test_customer_portal_exposes_route_milestones_with_plan_and_fact(): void
    {
        if (! Schema::hasTable('order_portal_invites') || ! Schema::hasTable('route_points')) {
            $this->markTestSkipped('Required tables are not applied.');
        }

        [$invite, $token, $order] = $this->createCustomerInvite(withOrder: true);

        $order->forceFill(['status' => 'in_progress'])->save();

        $leg = OrderLeg::query()->create([
            'order_id' => $order->id,
            'sequence' => 0,
            'type' => 'transport',
        ]);

        RoutePoint::query()->create([
            'order_leg_id' => $leg->id,
            'type' => 'loading',
            'sequence' => 0,
            'address' => 'Москва, склад 1',
            'planned_date' => '2026-07-20',
            'actual_date' => '2026-07-20',
        ]);

        RoutePoint::query()->create([
            'order_leg_id' => $leg->id,
            'type' => 'unloading',
            'sequence' => 1,
            'address' => 'Казань, склад 2',
            'planned_date' => '2026-07-22',
            'actual_date' => null,
        ]);

        $this->get(route('portal.customer.show', ['token' => $token]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Portal/CustomerDocuments')
                ->where('trip_status.code', 'in_progress')
                ->where('trip_status.label', 'Выполняется')
                ->where('route_milestones.0.title', 'Погрузка')
                ->where('route_milestones.0.planned_date', '2026-07-20')
                ->where('route_milestones.0.actual_date', '2026-07-20')
                ->where('route_milestones.1.title', 'Выгрузка')
                ->where('route_milestones.1.planned_date', '2026-07-22')
                ->where('route_milestones.1.actual_date', null));
    }

    public function test_customer_portal_page_rejects_expired_invite(): void
    {
        if (! Schema::hasTable('order_portal_invites')) {
            $this->markTestSkipped('order_portal_invites migration is not applied.');
        }

        [$invite, $token] = $this->createCustomerInvite();
        $invite->forceFill(['expires_at' => now()->subMinute()])->save();

        $this->get(route('portal.customer.show', ['token' => $token]))
            ->assertStatus(410);
    }

    public function test_customer_portal_page_rejects_revoked_invite(): void
    {
        if (! Schema::hasTable('order_portal_invites')) {
            $this->markTestSkipped('order_portal_invites migration is not applied.');
        }

        [$invite, $token] = $this->createCustomerInvite();
        $invite->forceFill(['revoked_at' => now()])->save();

        $this->get(route('portal.customer.show', ['token' => $token]))
            ->assertStatus(410);
    }

    public function test_customer_portal_can_upload_document(): void
    {
        if (! Schema::hasTable('order_portal_invites') || ! Schema::hasTable('order_documents')) {
            $this->markTestSkipped('Required tables are not applied.');
        }

        Storage::fake('local');

        [$invite, $token, $order] = $this->createCustomerInvite(withOrder: true);

        $slots = app(OrderCustomerPortalDocumentService::class)
            ->documentSlotsForInvite($invite->load('order.documents'));

        if ($slots === []) {
            $this->markTestSkipped('No customer document slots for this order.');
        }

        $slot = $slots[0];
        $type = $slot['type_options'][0]['value'] ?? 'request';

        $this->post(route('portal.customer.documents.store', ['token' => $token]), [
            'slot_kind' => $slot['slot_kind'],
            'requirement_slot_key' => $slot['slot_key'],
            'type' => $type,
            'file' => UploadedFile::fake()->create('packing.pdf', 100, 'application/pdf'),
        ], [
            'Accept' => 'application/json',
        ])->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('order_documents', [
            'order_id' => $order->id,
            'type' => $type,
        ]);
    }

    /**
     * @return array{0: OrderPortalInvite, 1: string, 2?: Order}
     */
    private function createCustomerInvite(bool $withOrder = false): array
    {
        $staff = $this->createManagerUser();
        $customer = Contractor::query()->create([
            'type' => 'customer',
            'name' => 'ООО Заказчик',
        ]);
        $order = Order::query()->create([
            'order_number' => 'ORD-CUST-02',
            'company_code' => 'ORD',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_active' => true,
            'customer_id' => $customer->id,
            'manager_id' => $staff->id,
        ]);

        $result = app(OrderPortalInviteService::class)->createCustomerDocumentsInvite($order, $staff);

        if ($withOrder) {
            return [$result['invite'], $result['token'], $order];
        }

        return [$result['invite'], $result['token']];
    }

    private function createManagerUser(): User
    {
        $role = DB::table('roles')->where('name', 'manager')->first();

        if ($role === null) {
            $roleId = DB::table('roles')->insertGetId([
                'name' => 'manager',
                'display_name' => 'Manager',
                'visibility_areas' => json_encode(['orders', 'documents']),
                'visibility_scopes' => json_encode(['orders' => 'own', 'documents' => 'own']),
                'columns_config' => json_encode([]),
                'permissions' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $roleId = (int) $role->id;
        }

        return User::factory()->create([
            'role_id' => $roleId,
        ]);
    }
}
