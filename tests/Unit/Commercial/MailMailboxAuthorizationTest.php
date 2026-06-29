<?php

namespace Tests\Unit\Commercial;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\MailMailboxAuthorization;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailMailboxAuthorizationTest extends TestCase
{
    #[Test]
    public function manager_can_access_own_thread_but_not_another_users_thread(): void
    {
        $mailRole = Role::query()->create([
            'name' => 'mail_manager',
            'visibility_areas' => ['mail'],
        ]);

        $owner = User::factory()->create([
            'role_id' => $mailRole->id,
            'email' => 'owner@example.com',
        ]);

        $other = User::factory()->create([
            'role_id' => $mailRole->id,
            'email' => 'other@example.com',
        ]);

        $ownThread = MailThread::query()->create([
            'subject' => 'Own thread',
            'mailbox_user_id' => $owner->id,
            'last_message_at' => now(),
        ]);

        $foreignThread = MailThread::query()->create([
            'subject' => 'Foreign thread',
            'mailbox_user_id' => $other->id,
            'last_message_at' => now(),
        ]);

        $auth = app(MailMailboxAuthorization::class);

        $this->assertTrue($auth->canAccessThread($owner, $ownThread));
        $this->assertFalse($auth->canAccessThread($owner, $foreignThread));
    }

    #[Test]
    public function admin_can_access_any_thread(): void
    {
        $adminRole = Role::query()->create([
            'name' => 'admin',
            'visibility_areas' => ['mail'],
        ]);

        $admin = User::factory()->create([
            'role_id' => $adminRole->id,
            'email' => 'admin@example.com',
        ]);

        $thread = MailThread::query()->create([
            'subject' => 'Someone else',
            'mailbox_user_id' => 999,
            'last_message_at' => now(),
        ]);

        $auth = app(MailMailboxAuthorization::class);

        $this->assertTrue($auth->canAccessThread($admin, $thread));
    }

    #[Test]
    public function message_access_follows_thread_access(): void
    {
        $mailRole = Role::query()->create([
            'name' => 'mail_user',
            'visibility_areas' => ['mail'],
        ]);

        $user = User::factory()->create([
            'role_id' => $mailRole->id,
            'email' => 'user@example.com',
        ]);

        $thread = MailThread::query()->create([
            'subject' => 'Thread',
            'mailbox_user_id' => $user->id,
            'last_message_at' => now(),
        ]);

        $message = MailMessage::query()->create([
            'mail_thread_id' => $thread->id,
            'direction' => MailMessage::DIRECTION_INBOUND,
            'from_email' => 'client@example.com',
            'to_emails' => ['user@example.com'],
            'subject' => 'Thread',
            'body_text' => 'Hello',
            'sent_at' => now(),
        ]);

        $auth = app(MailMailboxAuthorization::class);

        $this->assertTrue($auth->canAccessMessage($user, $message));
    }
}
