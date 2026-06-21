<?php

namespace Tests\Unit\Support\MailSync;

use App\Models\User;
use App\Support\MailSync\MailMailboxOwnerCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailMailboxOwnerCatalogTest extends TestCase
{
    #[Test]
    public function it_uses_first_name_token_as_short_label(): void
    {
        $user = new User([
            'name' => 'Иванов Иван',
            'email' => 'ivanov@example.com',
        ]);

        $this->assertSame('Иванов', MailMailboxOwnerCatalog::shortLabel($user));
    }

    #[Test]
    public function it_falls_back_to_email_local_part(): void
    {
        $user = new User([
            'name' => '',
            'email' => 'petrov@example.com',
        ]);

        $this->assertSame('petrov', MailMailboxOwnerCatalog::shortLabel($user));
    }
}
