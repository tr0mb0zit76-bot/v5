<?php

namespace Tests\Feature;

use App\Models\Contractor;
use App\Models\ContractorContact;
use App\Models\Order;
use App\Models\OrderPortalInvite;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CounterpartyMessengerTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_open_counterparty_thread_with_external_carrier(): void
    {
        if (! Schema::hasColumn('conversations', 'channel')) {
            $this->markTestSkipped('conversations.channel migration is not applied.');
        }

        [$staff, $external, $contractor] = $this->createCarrierCounterpartyFixtures();

        $this->actingAs($staff)->postJson(route('messenger.conversations.open-counterparty'), [
            'contractor_id' => $contractor->id,
            'external_party' => 'carrier',
        ])->assertOk()
            ->assertJsonPath('conversation.channel', 'counterparty')
            ->assertJsonPath('conversation.external_party', 'carrier')
            ->assertJsonPath('conversation.other_user.id', $external->id);

        $this->assertDatabaseHas('conversations', [
            'channel' => 'counterparty',
            'contractor_id' => $contractor->id,
            'external_party' => 'carrier',
            'primary_staff_user_id' => $staff->id,
        ]);
    }

    public function test_open_counterparty_with_order_posts_system_message(): void
    {
        if (! Schema::hasColumn('chat_messages', 'order_id')) {
            $this->markTestSkipped('chat_messages.order_id migration is not applied.');
        }

        [$staff, $external, $contractor] = $this->createCarrierCounterpartyFixtures();

        $order = Order::query()->create([
            'order_number' => 'ORD-CP-001',
            'company_code' => 'ORD',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_active' => true,
        ]);

        $this->linkCarrierToOrder($order, $contractor, $staff);

        $open = $this->actingAs($staff)->postJson(route('messenger.conversations.open-counterparty'), [
            'contractor_id' => $contractor->id,
            'external_party' => 'carrier',
            'order_id' => $order->id,
        ])->assertOk();

        $conversationId = (int) $open->json('conversation.id');

        $this->actingAs($staff)->getJson(route('messenger.conversations.messages', $conversationId))
            ->assertOk()
            ->assertJsonPath('messages.0.message_type', 'system')
            ->assertJsonPath('messages.0.order_id', $order->id)
            ->assertJsonFragment(['body' => 'Обсуждаем заказ ORD-CP-001']);
    }

    public function test_external_user_sees_only_counterparty_conversations(): void
    {
        if (! Schema::hasColumn('conversations', 'channel')) {
            $this->markTestSkipped('conversations.channel migration is not applied.');
        }

        [$staff, $external, $contractor] = $this->createCarrierCounterpartyFixtures();
        $otherStaff = User::factory()->create();

        $this->actingAs($staff)->postJson(route('messenger.conversations.open'), [
            'user_id' => $otherStaff->id,
        ])->assertOk();

        $this->actingAs($staff)->postJson(route('messenger.conversations.open-counterparty'), [
            'contractor_id' => $contractor->id,
            'external_party' => 'carrier',
        ])->assertOk();

        $staffConversations = $this->actingAs($staff)->getJson(route('messenger.conversations.index'))
            ->assertOk()
            ->json('conversations');

        $this->assertGreaterThanOrEqual(2, count($staffConversations));

        $externalConversations = $this->actingAs($external)->getJson(route('messenger.conversations.index'))
            ->assertOk()
            ->json('conversations');

        $this->assertCount(1, $externalConversations);
        $this->assertSame('counterparty', $externalConversations[0]['channel']);
    }

    public function test_group_rejects_two_external_participants(): void
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            $this->markTestSkipped('users.is_external migration is not applied.');
        }

        $staff = User::factory()->create();
        [, $carrierExternal] = $this->createExternalUser('carrier', 'carrier-a@test.test');
        [, $customerExternal] = $this->createExternalUser('customer', 'customer-a@test.test');

        $this->actingAs($staff)->postJson(route('messenger.conversations.groups.store'), [
            'title' => 'Смешанная группа',
            'user_ids' => [$carrierExternal->id, $customerExternal->id],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['user_ids']);
    }

    public function test_external_user_cannot_open_counterparty_endpoint(): void
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            $this->markTestSkipped('users.is_external migration is not applied.');
        }

        [, $external, $contractor] = $this->createCarrierCounterpartyFixtures();

        $this->actingAs($external)->postJson(route('messenger.conversations.open-counterparty'), [
            'contractor_id' => $contractor->id,
            'external_party' => 'carrier',
        ])->assertForbidden();
    }

    public function test_external_user_cannot_open_direct_chat_with_unrelated_staff(): void
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            $this->markTestSkipped('users.is_external migration is not applied.');
        }

        [, $external] = $this->createExternalUser('carrier', 'carrier-direct@test.test');
        $unrelatedStaff = User::factory()->create();

        $this->actingAs($external)->postJson(route('messenger.conversations.open'), [
            'user_id' => $unrelatedStaff->id,
        ])->assertForbidden();
    }

    public function test_external_user_can_reopen_direct_chat_with_existing_counterparty_staff(): void
    {
        if (! Schema::hasColumn('conversations', 'channel')) {
            $this->markTestSkipped('conversations.channel migration is not applied.');
        }

        [$staff, $external, $contractor] = $this->createCarrierCounterpartyFixtures();

        $this->actingAs($staff)->postJson(route('messenger.conversations.open-counterparty'), [
            'contractor_id' => $contractor->id,
            'external_party' => 'carrier',
        ])->assertOk();

        $this->actingAs($external)->postJson(route('messenger.conversations.open'), [
            'user_id' => $staff->id,
        ])->assertOk()
            ->assertJsonPath('conversation.channel', 'counterparty');
    }

    public function test_counterparty_message_rejects_inaccessible_order_id(): void
    {
        if (! Schema::hasColumn('chat_messages', 'order_id')) {
            $this->markTestSkipped('chat_messages.order_id migration is not applied.');
        }

        [$staff, $external, $contractor] = $this->createCarrierCounterpartyFixtures();
        $accessibleOrder = Order::query()->create([
            'order_number' => 'ORD-CP-ACCESS',
            'company_code' => 'ORD',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_active' => true,
        ]);
        $this->linkCarrierToOrder($accessibleOrder, $contractor, $staff);

        $hiddenOrder = Order::query()->create([
            'order_number' => 'ORD-CP-HIDDEN',
            'company_code' => 'ORD',
            'order_date' => now()->toDateString(),
            'status' => 'draft',
            'is_active' => true,
        ]);

        $open = $this->actingAs($staff)->postJson(route('messenger.conversations.open-counterparty'), [
            'contractor_id' => $contractor->id,
            'external_party' => 'carrier',
            'order_id' => $accessibleOrder->id,
        ])->assertOk();

        $conversationId = (int) $open->json('conversation.id');

        $this->actingAs($external)->postJson(route('messenger.conversations.messages.store', $conversationId), [
            'body' => 'Документ по чужому заказу',
            'order_id' => $hiddenOrder->id,
        ])->assertForbidden();
    }

    public function test_external_mobile_api_document_chips_are_empty(): void
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            $this->markTestSkipped('users.is_external migration is not applied.');
        }

        [, $external] = $this->createExternalUser('carrier', 'carrier-docchips@test.test');
        Sanctum::actingAs($external);

        $this->getJson(route('mobile.messenger.document-chips', ['q' => 'акт']))
            ->assertOk()
            ->assertJsonPath('documents', []);
    }

    /**
     * @return array{0: User, 1: User, 2: Contractor}
     */
    private function createCarrierCounterpartyFixtures(): array
    {
        $staff = User::factory()->create();
        [$contractor, $external] = $this->createExternalUser('carrier', 'carrier-fixture@test.test');

        return [$staff, $external, $contractor];
    }

    /**
     * @return array{0: Contractor, 1: User}
     */
    private function createExternalUser(string $contractorType, string $email): array
    {
        $roleName = $contractorType === 'carrier' ? 'counterparty_carrier' : 'counterparty_customer';
        $role = Role::query()->where('name', $roleName)->first();
        $this->assertNotNull($role, "Role {$roleName} must exist. Run migrations.");

        $contractor = Contractor::query()->create([
            'type' => $contractorType,
            'name' => 'Контрагент '.$contractorType,
        ]);

        $contact = ContractorContact::query()->create([
            'contractor_id' => $contractor->id,
            'full_name' => 'Контакт '.$contractorType,
            'email' => $email,
            'is_primary' => true,
            'is_traklo_primary' => true,
        ]);

        $external = User::factory()->create([
            'email' => $email,
            'is_external' => true,
            'is_active' => true,
            'contractor_id' => $contractor->id,
            'contractor_contact_id' => $contact->id,
            'external_party' => $contractorType,
            'role_id' => $role->id,
        ]);

        return [$contractor, $external];
    }

    private function linkCarrierToOrder(Order $order, Contractor $contractor, User $staff): void
    {
        if (Schema::hasColumn('orders', 'performers')) {
            $order->forceFill([
                'performers' => [
                    ['contractor_id' => $contractor->id],
                ],
            ])->save();

            return;
        }

        if (Schema::hasTable('order_portal_invites')) {
            OrderPortalInvite::query()->create([
                'order_id' => $order->id,
                'contractor_id' => $contractor->id,
                'stage' => 'leg_1',
                'carrier_slot' => 1,
                'purpose' => OrderPortalInvite::PURPOSE_CARRIER_FLEET,
                'token_hash' => hash('sha256', 'test-invite-token'),
                'created_by' => $staff->id,
                'expires_at' => now()->addDays(7),
            ]);

            return;
        }

        $this->markTestSkipped('Cannot link carrier contractor to order in this schema.');
    }
}
