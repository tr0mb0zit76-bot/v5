<?php

namespace Tests\Unit\Support\MailSync;

use App\Models\MailBlockedSender;
use App\Support\MailSync\ImportedMailMessage;
use App\Support\MailSync\MailImportAllowance;
use App\Support\MailSync\MailSyncSpamBlocklist;
use Tests\TestCase;

class MailImportAllowanceTest extends TestCase
{
    public function test_imports_any_inbound_sender_when_contractor_match_disabled(): void
    {
        config(['mail_sync.require_contractor_match' => false]);

        $checker = new MailImportAllowance;

        $message = new ImportedMailMessage(
            internetMessageId: '<unknown@marketing.ru>',
            direction: 'inbound',
            fromEmail: 'newsletter@marketing.ru',
            toEmails: ['manager@avtoaliyans.ru'],
            ccEmails: [],
            subject: 'Рассылка',
            bodyText: 'Текст',
            bodyHtml: null,
            inReplyTo: null,
            sentAt: null,
            folder: 'INBOX',
        );

        $this->assertTrue($checker->shouldImport($message, 'manager@avtoaliyans.ru'));
    }

    public function test_blocks_inbound_sender_from_spam_list(): void
    {
        config(['mail_sync.require_contractor_match' => false]);

        MailBlockedSender::query()->create([
            'email' => 'newsletter@marketing.ru',
            'note' => 'Рассылка',
        ]);

        MailSyncSpamBlocklist::forgetCache();

        $checker = new MailImportAllowance;

        $message = new ImportedMailMessage(
            internetMessageId: '<spam@marketing.ru>',
            direction: 'inbound',
            fromEmail: 'newsletter@marketing.ru',
            toEmails: ['manager@avtoaliyans.ru'],
            ccEmails: [],
            subject: 'Рассылка',
            bodyText: 'Текст',
            bodyHtml: null,
            inReplyTo: null,
            sentAt: null,
            folder: 'INBOX',
        );

        $this->assertFalse($checker->shouldImport($message, 'manager@avtoaliyans.ru'));
    }

    public function test_does_not_block_outbound_messages_by_spam_list(): void
    {
        config(['mail_sync.require_contractor_match' => false]);

        MailBlockedSender::query()->create([
            'email' => 'manager@avtoaliyans.ru',
        ]);

        MailSyncSpamBlocklist::forgetCache();

        $checker = new MailImportAllowance;

        $message = new ImportedMailMessage(
            internetMessageId: '<sent@avtoaliyans.ru>',
            direction: 'outbound',
            fromEmail: 'manager@avtoaliyans.ru',
            toEmails: ['client@example.com'],
            ccEmails: [],
            subject: 'Ответ',
            bodyText: 'Текст',
            bodyHtml: null,
            inReplyTo: null,
            sentAt: null,
            folder: 'Sent',
        );

        $this->assertTrue($checker->shouldImport($message, 'manager@avtoaliyans.ru'));
    }
}
