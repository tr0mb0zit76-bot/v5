<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserMobileDevice;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessengerTest extends TestCase
{
    public function test_open_direct_creates_conversation_and_lists_it(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->postJson(route('messenger.conversations.open'), [
            'user_id' => $b->id,
        ])->assertOk()->assertJsonPath('conversation.other_user.id', $b->id);

        $this->assertDatabaseHas('conversations', ['type' => 'direct']);
        $this->assertDatabaseCount('conversation_participants', 2);
    }

    public function test_mobile_api_can_open_direct_and_send_message(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        Sanctum::actingAs($a);

        $open = $this->postJson(route('mobile.messenger.conversations.open'), [
            'user_id' => $b->id,
        ])->assertOk()
            ->assertJsonPath('conversation.other_user.id', $b->id);

        $conversationId = (int) $open->json('conversation.id');

        $this->postJson(route('mobile.messenger.conversations.messages.store', $conversationId), [
            'body' => 'Сообщение из мобильного API',
        ])->assertOk()
            ->assertJsonPath('message.body', 'Сообщение из мобильного API');
    }

    public function test_colleagues_endpoint_includes_phone_for_mobile_contacts(): void
    {
        $viewer = User::factory()->create();
        $colleague = User::factory()->create([
            'name' => 'Алена Менеджер',
            'phone' => '+79990001122',
        ]);

        $this->actingAs($viewer)->getJson(route('messenger.colleagues'))
            ->assertOk()
            ->assertJsonFragment([
                'id' => $colleague->id,
                'name' => 'Алена Менеджер',
                'phone' => '+79990001122',
            ]);
    }

    public function test_colleagues_endpoint_hides_cursor_service_user(): void
    {
        $viewer = User::factory()->create();
        $serviceUser = User::factory()->create([
            'name' => 'cursor',
            'email' => 'cursor@example.test',
        ]);

        $this->actingAs($viewer)->getJson(route('messenger.colleagues'))
            ->assertOk()
            ->assertJsonMissing([
                'id' => $serviceUser->id,
            ]);
    }

    public function test_user_can_send_message_and_other_sees_unread(): void
    {
        $a = User::factory()->create(['name' => 'Cursor Bot']);
        $b = User::factory()->create();

        $open = $this->actingAs($a)->postJson(route('messenger.conversations.open'), [
            'user_id' => $b->id,
        ])->assertOk();

        $conversationId = (int) $open->json('conversation.id');

        $this->actingAs($a)->postJson(route('messenger.conversations.messages.store', $conversationId), [
            'body' => 'Привет из теста',
        ])->assertOk()
            ->assertJsonPath('message.author_name', 'Cursor Bot');

        $this->actingAs($b)->getJson(route('messenger.conversations.index'))
            ->assertOk()
            ->assertJsonPath('conversations.0.unread_count', 1)
            ->assertJsonPath('conversations.0.last_message.author_name', 'Cursor Bot')
            ->assertJsonPath('conversations.0.last_message.user_id', $a->id);

        $this->actingAs($b)->getJson(route('messenger.conversations.messages', $conversationId))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Привет из теста')
            ->assertJsonPath('messages.0.author_name', 'Cursor Bot');

        $this->actingAs($b)->getJson(route('messenger.conversations.index'))
            ->assertOk()
            ->assertJsonPath('conversations.0.unread_count', 0);
    }

    public function test_new_message_updates_messenger_without_bell_notification(): void
    {
        config([
            'fcm.enabled' => true,
            'fcm.project_id' => 'test-project',
            'fcm.access_token_override' => 'fake-access-token',
        ]);

        Http::fake([
            'https://fcm.googleapis.com/*' => Http::response(['name' => 'projects/test/messages/1']),
        ]);

        $a = User::factory()->create();
        $b = User::factory()->create();

        UserMobileDevice::query()->create([
            'user_id' => $b->id,
            'device_key' => '33333333-3333-4333-8333-333333333333',
            'pin_hash' => bcrypt('1234'),
            'fcm_token' => 'recipient-device-token',
        ]);

        $open = $this->actingAs($a)->postJson(route('messenger.conversations.open'), [
            'user_id' => $b->id,
        ])->assertOk();

        $conversationId = (int) $open->json('conversation.id');

        $message = $this->actingAs($a)->postJson(route('messenger.conversations.messages.store', $conversationId), [
            'body' => 'Проверь рейс',
        ])->assertOk();

        $this->actingAs($b)->getJson(route('messenger.conversations.index'))
            ->assertOk()
            ->assertJsonPath('conversations.0.unread_count', 1);

        $this->assertSame(0, $b->fresh()->notifications()->count());
        $this->assertSame(0, $a->fresh()->notifications()->count());

        Http::assertSent(function ($request) use ($conversationId, $message): bool {
            return str_contains($request->url(), 'fcm.googleapis.com')
                && ($request->data()['message']['token'] ?? null) === 'recipient-device-token'
                && ($request->data()['message']['data']['kind'] ?? null) === 'chat_message'
                && ($request->data()['message']['data']['conversation_id'] ?? null) === (string) $conversationId
                && ($request->data()['message']['data']['message_id'] ?? null) === (string) $message->json('message.id');
        });
    }

    public function test_create_group_adds_participants_and_lists_title(): void
    {
        $creator = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($creator)->postJson(route('messenger.conversations.groups.store'), [
            'title' => 'Документы',
            'user_ids' => [$a->id, $b->id],
        ])->assertOk()
            ->assertJsonPath('conversation.type', 'group')
            ->assertJsonPath('conversation.title', 'Документы')
            ->assertJsonPath('conversation.member_count', 3);

        $this->assertDatabaseHas('conversations', ['type' => 'group', 'title' => 'Документы']);
        $this->assertDatabaseCount('conversation_participants', 3);
    }

    public function test_group_member_can_exchange_messages_with_link(): void
    {
        $creator = User::factory()->create();
        $member = User::factory()->create();

        $open = $this->actingAs($creator)->postJson(route('messenger.conversations.groups.store'), [
            'title' => 'Ссылки',
            'user_ids' => [$member->id],
        ])->assertOk();

        $conversationId = (int) $open->json('conversation.id');

        $this->actingAs($creator)->postJson(route('messenger.conversations.messages.store', $conversationId), [
            'body' => 'Документ: https://example.com/file.pdf',
        ])->assertOk();

        $this->actingAs($member)->getJson(route('messenger.conversations.messages', $conversationId))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Документ: https://example.com/file.pdf');
    }

    public function test_group_message_can_address_recipient(): void
    {
        $creator = User::factory()->create();
        $member = User::factory()->create();

        $open = $this->actingAs($creator)->postJson(route('messenger.conversations.groups.store'), [
            'title' => 'Команда',
            'user_ids' => [$member->id],
        ])->assertOk();

        $conversationId = (int) $open->json('conversation.id');

        $this->actingAs($creator)->postJson(route('messenger.conversations.messages.store', $conversationId), [
            'body' => 'Для тебя',
            'recipient_user_id' => $member->id,
        ])->assertOk()
            ->assertJsonPath('message.recipient_user_id', $member->id)
            ->assertJsonPath('message.recipient_name', $member->name);

        $this->actingAs($member)->getJson(route('messenger.conversations.messages', $conversationId))
            ->assertOk()
            ->assertJsonPath('messages.0.recipient_user_id', $member->id);
    }

    public function test_group_message_rejects_recipient_not_in_group(): void
    {
        $creator = User::factory()->create();
        $member = User::factory()->create();
        $outsider = User::factory()->create();

        $open = $this->actingAs($creator)->postJson(route('messenger.conversations.groups.store'), [
            'title' => 'Команда',
            'user_ids' => [$member->id],
        ])->assertOk();

        $conversationId = (int) $open->json('conversation.id');

        $this->actingAs($creator)->postJson(route('messenger.conversations.messages.store', $conversationId), [
            'body' => 'Тест',
            'recipient_user_id' => $outsider->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['recipient_user_id']);
    }

    public function test_document_chips_endpoint_returns_json(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('messenger.document-chips'))
            ->assertOk()
            ->assertJsonStructure(['documents']);
    }

    public function test_document_chips_accepts_optional_search_query(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('messenger.document-chips', ['q' => 'счёт']))
            ->assertOk()
            ->assertJsonStructure(['documents']);
    }
}
