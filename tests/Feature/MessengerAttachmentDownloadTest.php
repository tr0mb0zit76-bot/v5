<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MessengerAttachmentDownloadTest extends TestCase
{
    public function test_conversation_participant_can_open_attachment(): void
    {
        [$url] = $this->createAttachment(
            User::factory()->create(),
            $participant = User::factory()->create(),
        );

        $this->actingAs($participant)->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_authenticated_non_participant_cannot_open_attachment(): void
    {
        [$url] = $this->createAttachment(
            User::factory()->create(),
            User::factory()->create(),
        );

        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    public function test_guest_cannot_open_attachment_with_valid_signature(): void
    {
        [$url] = $this->createAttachment(
            User::factory()->create(),
            User::factory()->create(),
        );
        Auth::guard()->logout();

        $this->getJson($url)->assertUnauthorized();
    }

    public function test_participant_cannot_open_attachment_with_tampered_signature(): void
    {
        [$url] = $this->createAttachment(
            User::factory()->create(),
            $participant = User::factory()->create(),
        );

        $this->actingAs($participant)->get($url.'&download=1')->assertForbidden();
    }

    public function test_participant_cannot_open_attachment_with_expired_signature(): void
    {
        [, $attachmentId] = $this->createAttachment(
            User::factory()->create(),
            $participant = User::factory()->create(),
        );
        $url = URL::temporarySignedRoute(
            'messenger.attachments.show',
            now()->subMinute(),
            ['attachment' => $attachmentId],
        );

        $this->actingAs($participant)->get($url)->assertForbidden();
    }

    public function test_external_mobile_participant_can_open_attachment(): void
    {
        if (! Schema::hasColumn('users', 'is_external')) {
            $this->markTestSkipped('users.is_external migration is not applied.');
        }

        $externalParticipant = User::factory()->create(['is_external' => true]);
        [$url, , $conversationId] = $this->createAttachment(
            User::factory()->create(),
            User::factory()->create(),
        );
        Conversation::query()->findOrFail($conversationId)->participants()->attach(
            $externalParticipant->id,
            ['role' => 'member'],
        );

        Sanctum::actingAs($externalParticipant);

        $this->get($url)
            ->assertOk()
            ->assertHeader('content-type', 'image/jpeg');
    }

    public function test_attachment_extension_cannot_spoof_disallowed_content_type(): void
    {
        $sender = User::factory()->create();
        $recipient = User::factory()->create();
        $conversationId = (int) $this->actingAs($sender)
            ->postJson(route('messenger.conversations.open'), ['user_id' => $recipient->id])
            ->assertOk()
            ->json('conversation.id');

        $this->actingAs($sender)->post(
            route('messenger.conversations.messages.store', $conversationId),
            [
                'body' => '',
                'attachments' => [
                    UploadedFile::fake()->createWithContent('payload.jpg', '<?php echo "unsafe";'),
                ],
            ],
            ['Accept' => 'application/json'],
        )->assertUnprocessable();

        $this->assertDatabaseCount('chat_message_attachments', 0);
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function createAttachment(User $sender, User $recipient): array
    {
        Storage::fake('local');

        $conversationId = (int) $this->actingAs($sender)
            ->postJson(route('messenger.conversations.open'), ['user_id' => $recipient->id])
            ->assertOk()
            ->json('conversation.id');

        $response = $this->actingAs($sender)->post(
            route('messenger.conversations.messages.store', $conversationId),
            [
                'body' => '',
                'attachments' => [UploadedFile::fake()->image('photo.jpg', 320, 200)],
            ],
            ['Accept' => 'application/json'],
        )->assertOk();

        return [
            (string) $response->json('message.attachments.0.preview_url'),
            (int) $response->json('message.attachments.0.id'),
            $conversationId,
        ];
    }
}
