<?php

namespace Tests\Unit\Support\MailSync;

use App\Models\User;
use App\Support\MailSync\MailSyncMailboxEligibility;
use Tests\TestCase;

class MailSyncMailboxEligibilityTest extends TestCase
{
    public function test_corporate_mailbox_is_eligible(): void
    {
        config(['mail_sync.mailbox_domains' => ['avtoaliyans.ru']]);

        $this->assertTrue(MailSyncMailboxEligibility::isEligibleEmail('manager@avtoaliyans.ru'));
        $this->assertNull(MailSyncMailboxEligibility::ineligibilityReason(new User([
            'email' => 'manager@avtoaliyans.ru',
        ])));
    }

    public function test_personal_mailbox_is_not_eligible(): void
    {
        config(['mail_sync.mailbox_domains' => ['avtoaliyans.ru']]);

        $this->assertFalse(MailSyncMailboxEligibility::isEligibleEmail('user@mail.ru'));

        $reason = MailSyncMailboxEligibility::ineligibilityReason(new User([
            'email' => 'user@mail.ru',
        ]));

        $this->assertNotNull($reason);
        $this->assertStringContainsString('@avtoaliyans.ru', $reason);
    }
}
