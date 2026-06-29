<?php

namespace Tests\Unit\Commercial;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\MailInboxSyncService;
use App\Support\MailSync\ImportedMailMessage;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailInboxImportHtmlAttachmentsTest extends TestCase
{
    #[Test]
    public function it_imports_html_body_and_inbound_attachments(): void
    {
        Storage::fake('local');

        $role = Role::query()->create([
            'name' => 'mail_manager',
            'visibility_areas' => ['mail'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'manager@example.com',
        ]);

        $imported = new ImportedMailMessage(
            internetMessageId: '<inbound-test@example.com>',
            direction: MailMessage::DIRECTION_INBOUND,
            fromEmail: 'client@example.com',
            toEmails: ['manager@example.com'],
            ccEmails: [],
            subject: 'Документы',
            bodyText: 'См. вложение',
            bodyHtml: '<p><strong>См. вложение</strong></p>',
            inReplyTo: null,
            sentAt: now(),
            folder: 'INBOX',
            rawAttachments: [[
                'filename' => 'scan.pdf',
                'content' => '%PDF-1.4 test',
                'mime_type' => 'application/pdf',
                'size' => 13,
            ]],
        );

        $message = app(MailInboxSyncService::class)->importMessage($user, $imported);

        $this->assertInstanceOf(MailMessage::class, $message);
        $this->assertSame('<p><strong>См. вложение</strong></p>', $message->body_html);
        $this->assertIsArray($message->attachments);
        $this->assertCount(1, $message->attachments);
        $this->assertSame('scan.pdf', $message->attachments[0]['original_name'] ?? null);
        Storage::disk('local')->assertExists($message->attachments[0]['file_path']);

        $thread = MailThread::query()->find($message->mail_thread_id);
        $this->assertNotNull($thread);
        $this->assertSame($user->id, $thread->mailbox_user_id);
    }

    #[Test]
    public function it_imports_message_with_long_subject(): void
    {
        $role = Role::query()->create([
            'name' => 'mail_manager_long_subject',
            'visibility_areas' => ['mail'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'long-subject@example.com',
        ]);

        $longSubject = 'Встречное предложение на груз: "#UJJ5874, '.str_repeat('Оборудование и запчасти, ', 40).'Забайкальск-Белгород';

        $imported = new ImportedMailMessage(
            internetMessageId: '<long-subject@example.com>',
            direction: MailMessage::DIRECTION_INBOUND,
            fromEmail: 'ati@example.com',
            toEmails: ['long-subject@example.com'],
            ccEmails: [],
            subject: $longSubject,
            bodyText: 'Текст',
            bodyHtml: null,
            inReplyTo: null,
            sentAt: now(),
            folder: 'INBOX',
        );

        $message = app(MailInboxSyncService::class)->importMessage($user, $imported);

        $this->assertInstanceOf(MailMessage::class, $message);
        $this->assertLessThanOrEqual(2000, mb_strlen((string) $message->subject));
        $this->assertSame($message->subject, MailThread::query()->find($message->mail_thread_id)?->subject);
    }
}
