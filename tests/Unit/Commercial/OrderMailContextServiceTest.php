<?php

namespace Tests\Unit\Commercial;

use App\Models\Contractor;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\Order;
use App\Models\Role;
use App\Models\User;
use App\Services\Commercial\OrderMailContextService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderMailContextServiceTest extends TestCase
{
    #[Test]
    public function it_returns_threads_and_compose_defaults_for_order(): void
    {
        $role = Role::query()->create([
            'name' => 'mail_manager',
            'visibility_areas' => ['mail'],
        ]);

        $user = User::factory()->create([
            'role_id' => $role->id,
            'email' => 'manager@example.com',
        ]);

        $customer = Contractor::query()->create([
            'name' => 'Клиент',
            'email' => 'client@example.com',
        ]);

        $order = Order::factory()->create([
            'order_number' => 'ORD-100',
            'customer_id' => $customer->id,
            'manager_id' => $user->id,
        ]);

        $thread = MailThread::query()->create([
            'subject' => 'По заказу',
            'order_id' => $order->id,
            'contractor_id' => $customer->id,
            'mailbox_user_id' => $user->id,
            'last_message_at' => now(),
        ]);

        MailMessage::query()->create([
            'mail_thread_id' => $thread->id,
            'direction' => MailMessage::DIRECTION_INBOUND,
            'from_email' => 'client@example.com',
            'to_emails' => ['manager@example.com'],
            'subject' => 'По заказу',
            'body_text' => 'Когда выезд?',
            'sent_at' => now(),
        ]);

        $service = app(OrderMailContextService::class);

        $threads = $service->threadSummariesForOrder($user, $order);
        $defaults = $service->composeDefaultsForOrder($order);

        $this->assertCount(1, $threads);
        $this->assertSame($thread->id, $threads[0]['id']);
        $this->assertNotNull($defaults);
        $this->assertSame($order->id, $defaults['order_id']);
        $this->assertSame(['client@example.com'], $defaults['to']);
        $this->assertSame('Заказ ORD-100', $defaults['subject']);
    }
}
