<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessengerTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_user_can_send_message_and_other_sees_unread(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $open = $this->actingAs($a)->postJson(route('messenger.conversations.open'), [
            'user_id' => $b->id,
        ])->assertOk();

        $conversationId = (int) $open->json('conversation.id');

        $this->actingAs($a)->postJson(route('messenger.conversations.messages.store', $conversationId), [
            'body' => 'Привет из теста',
        ])->assertOk();

        $this->actingAs($b)->getJson(route('messenger.conversations.index'))
            ->assertOk()
            ->assertJsonPath('conversations.0.unread_count', 1);

        $this->actingAs($b)->getJson(route('messenger.conversations.messages', $conversationId))
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Привет из теста');

        $this->actingAs($b)->getJson(route('messenger.conversations.index'))
            ->assertOk()
            ->assertJsonPath('conversations.0.unread_count', 0);
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
}
