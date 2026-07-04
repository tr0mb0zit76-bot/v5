<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class MessengerPollingTest extends TestCase
{
    public function test_messages_endpoint_supports_after_id_incremental_fetch(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $open = $this->actingAs($a)->postJson(route('messenger.conversations.open'), [
            'user_id' => $b->id,
        ])->assertOk();

        $conversationId = (int) $open->json('conversation.id');

        $first = $this->actingAs($a)->postJson(route('messenger.conversations.messages.store', $conversationId), [
            'body' => 'Первое сообщение',
        ])->assertOk();

        $firstMessageId = (int) $first->json('message.id');

        $this->actingAs($a)->postJson(route('messenger.conversations.messages.store', $conversationId), [
            'body' => 'Второе сообщение',
        ])->assertOk();

        $this->actingAs($b)->getJson(route('messenger.conversations.messages', [
            'conversation' => $conversationId,
            'after_id' => $firstMessageId,
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Второе сообщение');
    }
}
