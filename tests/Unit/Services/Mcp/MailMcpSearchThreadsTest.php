<?php

namespace Tests\Unit\Services\Mcp;

use App\Models\MailThread;
use App\Models\Role;
use App\Models\User;
use App\Services\Mcp\MailMcpService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailMcpSearchThreadsTest extends TestCase
{
    #[Test]
    public function it_filters_threads_by_employee_surname_for_admin(): void
    {
        $role = Role::query()->create([
            'name' => 'admin',
            'visibility_areas' => ['mail', 'admin'],
        ]);

        $admin = User::factory()->create([
            'role_id' => $role->id,
            'name' => 'Администратор',
            'email' => 'admin@example.com',
        ]);

        $sadykov = User::factory()->create([
            'role_id' => $role->id,
            'name' => 'Садыков Эмиль',
            'email' => 'ved@avtoaliyans.ru',
        ]);

        $other = User::factory()->create([
            'role_id' => $role->id,
            'name' => 'Иванов Иван',
            'email' => 'ivan@avtoaliyans.ru',
        ]);

        MailThread::query()->create([
            'subject' => 'Квиток Иванов борт 6.1х2.2',
            'mailbox_user_id' => $sadykov->id,
            'last_message_at' => now(),
        ]);

        MailThread::query()->create([
            'subject' => 'Чужая переписка',
            'mailbox_user_id' => $other->id,
            'last_message_at' => now()->subDay(),
        ]);

        $service = app(MailMcpService::class);

        $result = $service->searchThreads($admin, 'Садыков', 50);

        $this->assertSame($sadykov->id, $result['mailbox_user_id']);
        $this->assertSame(1, $result['mailbox_total_threads']);
        $this->assertCount(1, $result['threads']);
        $this->assertSame('Квиток Иванов борт 6.1х2.2', $result['threads'][0]['subject']);
        $this->assertSame('Садыков Эмиль', $result['threads'][0]['mailbox_owner_name']);
    }
}
