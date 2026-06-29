<?php

namespace Tests\Feature\Mail;

use App\Mail\CommercialOutboundMail;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailMailboxTest extends TestCase
{
    private function mailUser(): User
    {
        $role = Role::query()->create([
            'name' => 'mail_operator',
            'visibility_areas' => ['mail'],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
            'email' => 'manager@example.com',
        ]);
    }

    #[Test]
    public function user_can_send_mail_and_is_redirected_to_thread(): void
    {
        Mail::fake();

        $user = $this->mailUser();

        $response = $this->actingAs($user)->post(route('mail.send'), [
            'subject' => 'Коммерческое предложение',
            'body' => 'Добрый день!',
            'to' => ['client@example.com'],
            'cc' => ['cc@example.com'],
        ]);

        $thread = MailThread::query()->first();
        $message = MailMessage::query()->first();

        $this->assertNotNull($thread);
        $this->assertNotNull($message);
        $this->assertSame($user->id, $thread->mailbox_user_id);
        $this->assertSame('manager@example.com', $message->from_email);
        $this->assertSame(['cc@example.com'], $message->cc_emails);

        $response->assertRedirect(route('mail.threads.show', $thread));

        Mail::assertSent(CommercialOutboundMail::class);
    }

    #[Test]
    public function user_can_reply_in_accessible_thread(): void
    {
        Mail::fake();

        $user = $this->mailUser();

        $thread = MailThread::query()->create([
            'subject' => 'Вопрос по перевозке',
            'mailbox_user_id' => $user->id,
            'last_message_at' => now(),
            'last_inbound_at' => now(),
        ]);

        MailMessage::query()->create([
            'mail_thread_id' => $thread->id,
            'direction' => MailMessage::DIRECTION_INBOUND,
            'from_email' => 'client@example.com',
            'to_emails' => ['manager@example.com'],
            'subject' => 'Вопрос по перевозке',
            'body_text' => 'Когда будет машина?',
            'sent_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($user)->post(route('mail.threads.reply', $thread), [
            'to' => ['client@example.com'],
            'body' => 'Машина завтра.',
        ]);

        $response->assertRedirect(route('mail.threads.show', $thread));

        $this->assertSame(2, MailMessage::query()->where('mail_thread_id', $thread->id)->count());

        $latest = MailMessage::query()->orderByDesc('id')->first();
        $this->assertSame(MailMessage::DIRECTION_OUTBOUND, $latest?->direction);
        $this->assertStringContainsString('Re:', (string) $latest?->subject);

        Mail::assertSent(CommercialOutboundMail::class);
    }

    #[Test]
    public function user_cannot_open_foreign_thread(): void
    {
        $user = $this->mailUser();
        $other = User::factory()->create(['email' => 'other@example.com']);

        $thread = MailThread::query()->create([
            'subject' => 'Private',
            'mailbox_user_id' => $other->id,
            'last_message_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('mail.threads.show', $thread))
            ->assertForbidden();
    }

    #[Test]
    public function user_can_send_mail_with_attachments(): void
    {
        Mail::fake();
        Storage::fake('local');

        $user = $this->mailUser();
        $file = UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf');

        $this->actingAs($user)->post(route('mail.send'), [
            'subject' => 'Договор',
            'body' => 'Во вложении.',
            'to' => ['client@example.com'],
            'attachments' => [$file],
        ])->assertRedirect();

        $message = MailMessage::query()->first();

        $this->assertNotNull($message);
        $this->assertIsArray($message->attachments);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('contract.pdf', $message->attachments[0]['original_name'] ?? null);

        Mail::assertSent(CommercialOutboundMail::class, function (CommercialOutboundMail $mail): bool {
            return count($mail->outboundAttachments) === 1
                && $mail->outboundAttachments[0]['name'] === 'contract.pdf';
        });
    }

    #[Test]
    public function user_can_toggle_message_importance(): void
    {
        $user = $this->mailUser();

        $thread = MailThread::query()->create([
            'subject' => 'Important',
            'mailbox_user_id' => $user->id,
            'last_message_at' => now(),
        ]);

        $message = MailMessage::query()->create([
            'mail_thread_id' => $thread->id,
            'direction' => MailMessage::DIRECTION_INBOUND,
            'from_email' => 'client@example.com',
            'to_emails' => ['manager@example.com'],
            'subject' => 'Important',
            'body_text' => 'Please read',
            'sent_at' => now(),
            'is_important' => false,
        ]);

        $this->actingAs($user)
            ->patch(route('mail.messages.importance', $message), ['is_important' => true])
            ->assertRedirect();

        $this->assertTrue($message->fresh()->is_important);
    }
}
