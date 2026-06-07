<?php

namespace Tests\Unit\Services\Mcp;

use App\Models\MailThread;
use App\Models\Role;
use App\Models\User;
use App\Services\Mcp\MailMcpService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailMcpSearchThreadsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->schemaDropMany(['mail_messages', 'mail_threads', 'users', 'roles']);

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->json('visibility_areas')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->boolean('mail_sync_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('mail_threads', function (Blueprint $table): void {
            $table->id();
            $table->string('subject')->nullable();
            $table->unsignedBigInteger('mailbox_user_id')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mail_thread_id');
            $table->string('direction')->default('inbound');
            $table->string('from_email')->nullable();
            $table->text('body_text')->nullable();
            $table->string('subject')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

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
